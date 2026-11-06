<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Models\Role;
use App\Models\School;
use App\Models\User;
use App\Services\UserAccountService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(Request $request): View
    {
        $request->validate(['search' => ['nullable', 'string', 'max:200'], 'status' => ['nullable', 'in:active,suspended,deactivated'], 'account_type' => ['nullable', Rule::in(Role::ACCOUNT_ROLES)]]);
        $users = User::query()
            ->with(['roles', 'school'])
            ->when($request->filled('search'), fn ($q) => $q->where(function ($q) use ($request) {
                $term = '%'.$request->string('search')->toString().'%';
                $q->where('name', 'like', $term)->orWhere('username', 'like', $term)->orWhere('email', 'like', $term)->orWhereHas('school', fn ($school) => $school->where('official_name', 'like', $term)->orWhere('ministry_registration_number', 'like', $term));
            }))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->input('status')))
            ->when($request->filled('account_type'), fn ($q) => $q->whereHas('roles', fn ($roles) => $roles->where('slug', $request->input('account_type'))))
            ->latest()
            ->paginate(15)->withQueryString();

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
            'roles' => app(UserAccountService::class)->assignableRoles(auth()->user()),
            'schools' => School::whereNotIn('id', User::whereNotNull('school_id')->select('school_id'))->orderBy('official_name')->get(),
            'statuses' => $this->statuses(),
        ]);
    }

    public function store(StoreUserRequest $request): RedirectResponse
    {
        app(UserAccountService::class)->save($request);

        return redirect()
            ->route('administration.users.index')
            ->with('status', 'user account created.');
    }

    public function edit(User $user): View
    {
        $user->load('roles.permissions');
        app(UserAccountService::class)->authorizeTarget(auth()->user(), $user);

        return view('identity.users.edit', [
            'title' => 'edit user',
            'breadcrumbs' => [
                ['label' => 'administration'],
                ['label' => 'users', 'url' => route('administration.users.index')],
                ['label' => $user->name],
            ],
            'user' => $user,
            'roles' => app(UserAccountService::class)->assignableRoles(auth()->user()),
            'schools' => School::whereNotIn('id', User::whereNotNull('school_id')->where('id', '!=', $user->id)->select('school_id'))->orderBy('official_name')->get(),
            'statuses' => $this->statuses(),
        ]);
    }

    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        app(UserAccountService::class)->save($request, $user);
        if ($user->id === $request->user()->id) {
            $request->session()->regenerate();
        }

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
