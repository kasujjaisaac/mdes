<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\DevelopmentProject;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class ProjectController extends Controller
{
    public function index(): View
    {
        Gate::authorize('development.view');

        return view('operations.index', [
            'title' => 'projects',
            'records' => DevelopmentProject::query()->with('school')->latest()->paginate(20),
            'headers' => ['school', 'title', 'budget', 'status'],
            'rows' => fn (DevelopmentProject $project): array => [$project->school->name, $project->title, number_format((float) $project->approved_budget), $project->status],
            'createRoute' => route('development-needs.create'),
        ]);
    }
}
