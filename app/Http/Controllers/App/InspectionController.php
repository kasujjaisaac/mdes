<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\Inspection;
use App\Models\InspectionTemplate;
use App\Models\School;
use App\Services\InspectionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class InspectionController extends Controller
{
    public function index(): View
    {
        Gate::authorize('inspections.view');

        return view('operations.index', [
            'title' => 'inspections',
            'records' => Inspection::query()->with('school')->latest()->paginate(20),
            'headers' => ['school', 'type', 'planned date', 'status'],
            'rows' => fn (Inspection $inspection): array => [
                $inspection->school->name,
                $inspection->type,
                $inspection->planned_date?->toFormattedDateString(),
                $inspection->status,
            ],
            'createRoute' => route('inspections.create'),
        ]);
    }

    public function create(): View
    {
        Gate::authorize('inspections.manage');

        return view('operations.inspection-create', [
            'title' => 'schedule inspection',
            'schools' => School::query()->orderBy('name')->get(),
            'templates' => InspectionTemplate::query()->where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request, InspectionService $service): RedirectResponse
    {
        Gate::authorize('inspections.manage');
        $service->schedule($request->validate([
            'school_id' => ['required', 'exists:schools,id'],
            'inspection_template_id' => ['nullable', 'exists:inspection_templates,id'],
            'type' => ['required', 'string', 'max:80'],
            'purpose' => ['required', 'string', 'max:180'],
            'planned_date' => ['required', 'date'],
        ]));

        return redirect()->route('inspections.index')->with('status', 'inspection scheduled.');
    }
}
