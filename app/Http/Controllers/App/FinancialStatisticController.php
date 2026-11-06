<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\FinancialStatistic;
use App\Models\School;
use App\Services\FinancialStatisticsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class FinancialStatisticController extends Controller
{
    public function index(): View
    {
        Gate::authorize('financial-statistics.view');

        return view('operations.index', [
            'title' => 'financial statistics',
            'records' => FinancialStatistic::query()->with('school')->latest()->paginate(20),
            'headers' => ['school', 'year', 'budget', 'variance'],
            'rows' => fn (FinancialStatistic $stat): array => [$stat->school->name, $stat->budget_year, number_format((float) $stat->approved_budget_total), number_format((float) $stat->variance)],
            'createRoute' => route('financial-statistics.create'),
        ]);
    }

    public function create(): View
    {
        Gate::authorize('financial-statistics.manage');

        return view('operations.finance-create', ['title' => 'capture financial statistics', 'schools' => School::query()->orderBy('name')->get()]);
    }

    public function store(Request $request, FinancialStatisticsService $service): RedirectResponse
    {
        Gate::authorize('financial-statistics.manage');
        $data = $request->validate([
            'school_id' => ['required', 'exists:schools,id'],
            'budget_year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'budget_categories' => ['nullable', 'array'],
            'income_categories' => ['nullable', 'array'],
            'expenditure_categories' => ['nullable', 'array'],
            'variance_explanation' => ['nullable', 'string', 'max:1500'],
            'audited_accounts_available' => ['nullable', 'boolean'],
            'audit_date' => ['nullable', 'date'],
            'audit_status' => ['nullable', 'string', 'max:80'],
        ]);
        $service->save(School::query()->findOrFail($data['school_id']), $data);

        return redirect()->route('financial-statistics.index')->with('status', 'financial statistics saved.');
    }
}
