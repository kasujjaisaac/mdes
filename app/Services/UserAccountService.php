<?php

namespace App\Services;

use App\Actions\RecordAuditEvent;
use App\Models\Role;
use App\Models\School;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class UserAccountService
{
    public function assignableRoles(User $actor)
    {
        if (! $actor->isAdministrator()) return collect();

        return Role::with('permissions')->whereIn('slug', Role::ACCOUNT_ROLES)->orderBy('name')->get();
    }

    public function authorizeTarget(User $actor, User $target): void
    {
        $allowed = $this->assignableRoles($actor)->pluck('id');
        abort_unless($target->roles->every(fn ($role) => $allowed->contains($role->id)), 403);
    }

    public function save(Request $request, ?User $target = null): User
    {
        if ($target) {
            $this->authorizeTarget($request->user(), $target);
        }
        $data = $request->validated();
        $data['first_name'] = trim($data['first_name']);
        $data['last_name'] = trim($data['last_name']);
        $data['name'] = $data['first_name'].' '.$data['last_name'];
        $roleIds = collect($data['role_ids'])->map(fn ($id) => (int) $id)->sort()->values();
        $allowed = $this->assignableRoles($request->user())->pluck('id');
        $selectedRoles = Role::with('permissions')->whereIn('id', $roleIds)->get();
        if ($selectedRoles->contains('slug', Role::HEADTEACHER) && $selectedRoles->contains('slug', Role::SUPER_ADMINISTRATOR)) {
            throw ValidationException::withMessages(['role_ids' => 'School roles cannot be combined with roles that access all schools.']);
        }
        if ($roleIds->diff($allowed)->isNotEmpty()) {
            throw ValidationException::withMessages(['role_ids' => 'You cannot assign roles with access beyond your own.']);
        }
        $keepsUserManagement = $selectedRoles->contains('slug', Role::SUPER_ADMINISTRATOR);
        try {
            return DB::transaction(function () use ($request, $target, $data, $roleIds, $keepsUserManagement) {
                // Serialize administrator changes so concurrent requests cannot remove the last one.
                Role::where('slug', Role::SUPER_ADMINISTRATOR)->lockForUpdate()->first();
                $user = $target ? User::whereKey($target->id)->lockForUpdate()->firstOrFail() : new User;
                if ($target) {
                    $this->authorizeTarget($request->user(), $user);
                }
                $oldRoles = $user->exists ? $user->roles->pluck('id')->sort()->values() : collect();
                $superId = Role::where('slug', Role::SUPER_ADMINISTRATOR)->value('id');
                if ($target && $user->id === $request->user()->id && ($data['status'] !== User::STATUS_ACTIVE || ! $keepsUserManagement)) {
                    throw ValidationException::withMessages(['status' => 'You cannot disable your own account or remove your own user-management access.']);
                }
                if ($target && $user->isActive() && $user->isAdministrator() && ($data['status'] !== User::STATUS_ACTIVE || ! $roleIds->contains($superId)) && User::administrators()->where('status', User::STATUS_ACTIVE)->count() <= 1) {
                    throw ValidationException::withMessages(['role_ids' => 'Keep at least one active system super administrator.']);
                }
                $before = $target ? $user->only(['name', 'username', 'email', 'school_id', 'status']) + ['role_ids' => $oldRoles->all()] : null;
                $securityChange = $target && ($request->filled('password') || $user->email !== $data['email'] || $user->school_id !== (isset($data['school_id']) ? (int) $data['school_id'] : null) || $user->status !== $data['status'] || $oldRoles->all() !== $roleIds->all());
                $user->fill(collect($data)->except(['role_ids', 'password', 'password_confirmation', 'designation'])->all());
                if ($user->school_id && $user->school_id !== ($before['school_id'] ?? null)) {
                    $moe = School::whereKey($user->school_id)->value('ministry_registration_number');
                    if ($moe) {
                        $user->username = User::normalizeUsername($moe);
                    }
                }
                $user->designation = $roleIds->contains($superId) ? 'Admin' : 'School Headteacher';
                if ($request->filled('password')) {
                    $user->password = $data['password'];
                }
                if ($securityChange) {
                    $user->setRememberToken(Str::random(60));
                    $user->forceFill(['session_version' => (int) $user->session_version + 1]);
                }
                if ($target && $before['email'] !== $user->email) {
                    $user->forceFill(['email_verified_at' => null]);
                }
                $user->save();
                $user->roles()->sync($roleIds->all());
                if ($securityChange) {
                    DB::table('password_reset_tokens')->whereIn('email', [$before['email'], $user->email])->delete();
                    if (config('session.driver') === 'database') {
                        DB::connection(config('session.connection'))->table(config('session.table', 'sessions'))->where('user_id', $user->id)->when($user->id === $request->user()->id, fn ($q) => $q->where('id', '!=', $request->session()->getId()))->delete();
                    }
                }
                app(RecordAuditEvent::class)->handle($request, $target ? 'account.updated' : 'account.created', $user, $before, $user->only(['name', 'username', 'email', 'school_id', 'status']) + ['role_ids' => $roleIds->all(), 'password_changed' => $request->filled('password')]);

                if ($securityChange && $user->id === $request->user()->id) {
                    $request->session()->put('auth_session_version.'.$user->id, (int) $user->session_version);
                    $request->user()->setRawAttributes($user->getAttributes(), true);
                }

                return $user;
            });
        } catch (UniqueConstraintViolationException $exception) {
            throw ValidationException::withMessages(['email' => 'The email address or school was just assigned to another account. Check these details and try again.']);
        }
    }
}
