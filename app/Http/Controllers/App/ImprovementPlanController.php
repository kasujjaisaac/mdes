<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\ImprovementPlan;
use App\Models\School;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class ImprovementPlanController extends Controller
{
    public function index(): View
    {
        Gate::authorize('improvement-plans.view');

        return view('operations.index', [
            'title' => 'improvement plans',
            'records' => ImprovementPlan::query()->with('school')->latest()->paginate(20),
            'headers' => ['school', 'domain', 'problem', 'status'],
            'rows' => fn (ImprovementPlan $plan): array => [$plan->school->name, $plan->domain, str($plan->problem_statement)->limit(60), $plan->status],
            'createRoute' => route('improvement-plans.create'),
        ]);
    }

    public function create(): View
    {
        Gate::authorize('improvement-plans.manage');

        return view('operations.improvement-create', ['title' => 'create improvement plan', 'schools' => School::query()->orderBy('name')->get()]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('improvement-plans.manage');
        ImprovementPlan::query()->create($request->validate([
            'school_id' => ['required', 'exists:schools,id'],
            'problem_statement' => ['required', 'string', 'max:1500'],
            'domain' => ['required', 'string', 'max:80'],
            'baseline' => ['nullable', 'string', 'max:180'],
            'target' => ['nullable', 'string', 'max:180'],
            'success_indicator' => ['nullable', 'string', 'max:180'],
            'due_date' => ['nullable', 'date'],
        ]));

        return redirect()->route('improvement-plans.index')->with('status', 'improvement plan created.');
    }
}
