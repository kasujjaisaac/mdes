<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(): View
    {
        $users = User::query()
            ->with('roles')
            ->latest()
            ->paginate(15);

        return view('identity.users.index', [
            'title' => 'users',
            'breadcrumbs' => [
                ['label' => 'administration'],
                ['label' => 'users'],
            ],
            'actions' => [
                ['label' => 'create user', 'url' => route('administration.users.create')],
            ],
            'users' => $users,
        ]);
    }

    public function create(): View
    {
        return view('identity.users.create', [
            'title' => 'create user',
            'breadcrumbs' => [
                ['label' => 'administration'],
                ['label' => 'users', 'url' => route('administration.users.index')],
                ['label' => 'create'],
            ],
            'roles' => Role::query()->orderBy('name')->get(),
            'schools' => \App\Models\School::orderBy('official_name')->get(),
            'statuses' => $this->statuses(),
        ]);
    }

    public function store(StoreUserRequest $request): RedirectResponse
    {
        $user = User::query()->create([
            ...$request->safe()->except(['role_ids', 'password']),
            'password' => Hash::make($request->string('password')->toString()),
            'email_verified_at' => now(),
        ]);

        $user->roles()->sync($request->validated('role_ids', []));

        return redirect()
            ->route('administration.users.index')
            ->with('status', 'user account created.');
    }

    public function edit(User $user): View
    {
        $user->load('roles');

        return view('identity.users.edit', [
            'title' => 'edit user',
            'breadcrumbs' => [
                ['label' => 'administration'],
                ['label' => 'users', 'url' => route('administration.users.index')],
                ['label' => $user->name],
            ],
            'user' => $user,
            'roles' => Role::query()->orderBy('name')->get(),
            'schools' => \App\Models\School::orderBy('official_name')->get(),
            'statuses' => $this->statuses(),
        ]);
    }

    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        $data = $request->safe()->except(['role_ids', 'password']);

        if ($request->filled('password')) {
            $data['password'] = Hash::make($request->string('password')->toString());
        }

        $user->update($data);
        $user->roles()->sync($request->validated('role_ids', []));

        return redirect()
            ->route('administration.users.index')
            ->with('status', 'user account updated.');
    }

    /**
     * @return array<string, string>
     */
    private function statuses(): array
    {
        return [
            User::STATUS_ACTIVE => 'active',
            User::STATUS_SUSPENDED => 'suspended',
            User::STATUS_DEACTIVATED => 'deactivated',
        ];
    }
}
