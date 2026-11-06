<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\View\View;

class RoleController extends Controller
{
    public function index(): View
    {
        return view('identity.roles.index', [
            'title' => 'roles and permissions',
            'breadcrumbs' => [
                ['label' => 'administration'],
                ['label' => 'roles and permissions'],
            ],
            'roles' => Role::query()->with('permissions')->withCount('users')->orderBy('name')->get(),
            'permissions' => Permission::query()->orderBy('name')->get(),
        ]);
    }
}
