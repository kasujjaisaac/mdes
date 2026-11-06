<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\AuditEvent;
use App\Models\Permission;
use App\Models\ProductionReadinessReview;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\File;
use Illuminate\View\View;

class SystemController extends Controller
{
    public function profile(): View
    {
        return view('system.profile', [
            'title' => 'profile',
            'breadcrumbs' => [['label' => 'system'], ['label' => 'profile']],
            'user' => request()->user()->load('roles'),
        ]);
    }

    public function settings(): View
    {
        Gate::authorize('settings.view');

        return view('system.settings', [
            'title' => 'settings',
            'breadcrumbs' => [['label' => 'system'], ['label' => 'settings']],
            'stats' => [
                ['label' => 'users', 'value' => User::query()->count()],
                ['label' => 'roles', 'value' => Role::query()->count()],
                ['label' => 'permissions', 'value' => Permission::query()->count()],
                ['label' => 'readiness reviews', 'value' => ProductionReadinessReview::query()->count()],
            ],
        ]);
    }

    public function logs(): View
    {
        Gate::authorize('logs.view');

        $path = storage_path('logs/laravel.log');
        $lines = File::exists($path)
            ? collect(explode(PHP_EOL, File::get($path)))->filter()->take(-80)->values()
            : collect();

        return view('system.logs', [
            'title' => 'logs',
            'breadcrumbs' => [['label' => 'system'], ['label' => 'logs']],
            'lines' => $lines,
            'auditEvents' => AuditEvent::query()->latest()->limit(20)->get(),
        ]);
    }
}
