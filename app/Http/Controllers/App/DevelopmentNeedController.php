<?php

namespace App\Http\Controllers\App;

use App\Actions\ConvertNeedToProject;
use App\Http\Controllers\Controller;
use App\Models\DevelopmentNeed;
use App\Models\School;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class DevelopmentNeedController extends Controller
{
    public function index(): View
    {
        Gate::authorize('development.view');

        return view('operations.index', [
            'title' => 'development needs',
            'records' => DevelopmentNeed::query()->with('school')->latest()->paginate(20),
            'headers' => ['school', 'category', 'urgency', 'funding'],
            'rows' => fn (DevelopmentNeed $need): array => [$need->school->name, $need->category, $need->urgency, $need->funding_status],
            'createRoute' => route('development-needs.create'),
        ]);
    }

    public function create(): View
    {
        Gate::authorize('development.manage');

        return view('operations.need-create', ['title' => 'submit development need', 'schools' => School::query()->orderBy('name')->get()]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('development.manage');
        DevelopmentNeed::query()->create($request->validate([
            'school_id' => ['required', 'exists:schools,id'],
            'category' => ['required', 'string', 'max:80'],
            'description' => ['required', 'string', 'max:1500'],
            'justification' => ['nullable', 'string', 'max:1500'],
            'urgency' => ['required', 'in:low,normal,high,urgent'],
            'beneficiaries' => ['required', 'integer', 'min:0'],
            'estimated_cost' => ['required', 'numeric', 'min:0'],
            'school_contribution' => ['nullable', 'numeric', 'min:0'],
        ]));

        return redirect()->route('development-needs.index')->with('status', 'development need submitted.');
    }

    public function convert(DevelopmentNeed $need, Request $request, ConvertNeedToProject $action): RedirectResponse
    {
        Gate::authorize('development.manage');
        $need->update(['review_status' => 'approved']);
        $action->handle($need, $request->validate([
            'title' => ['required', 'string', 'max:180'],
            'approved_budget' => ['required', 'numeric', 'min:0'],
            'funder' => ['nullable', 'string', 'max:120'],
            'implementer' => ['nullable', 'string', 'max:120'],
        ]));

        return redirect()->route('projects.index')->with('status', 'need converted to project.');
    }
}
