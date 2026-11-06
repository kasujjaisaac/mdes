<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreJurisdictionAssignmentRequest;
use App\Http\Requests\StoreParishRequest;
use App\Http\Requests\UpdateParishRequest;
use App\Models\Diocese;
use App\Models\EducationCluster;
use App\Models\JurisdictionAssignment;
use App\Models\Parish;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DiocesanStructureController extends Controller
{
    public function parishes(Request $request): View
    {
        $request->validate(['search' => ['nullable', 'string', 'max:200'], 'status' => ['nullable', 'in:active,inactive']]);
        $parishes = Parish::with('diocese')->withCount('schools')
            ->when($request->filled('search'), fn ($q) => $q->where(function ($q) use ($request) {
                $term = '%'.$request->string('search')->toString().'%';
                $q->where('name', 'like', $term)->orWhere('code', 'like', $term);
            }))
            ->when(in_array($request->input('status'), ['active', 'inactive']), fn ($q) => $q->where('is_active', $request->input('status') === 'active'))
            ->orderBy('name')->paginate(20)->withQueryString();

        return view('diocesan-structure.parishes.index', ['title' => 'Parishes', 'parishes' => $parishes]);
    }

    public function index(): View
    {
        $parishes = Parish::query()
            ->withCount('schools')
            ->with('diocese')
            ->orderBy('name')
            ->get();

        return view('diocesan-structure.index', [
            'title' => 'diocesan structure',
            'breadcrumbs' => [
                ['label' => 'administration'],
                ['label' => 'diocesan structure'],
            ],
            'actions' => [
                ['label' => 'create parish', 'url' => route('diocesan-structure.parishes.create')],
                ['label' => 'assign jurisdiction', 'url' => route('diocesan-structure.assignments.create')],
            ],
            'parishes' => $parishes,
            'assignments' => JurisdictionAssignment::query()->with(['user', 'parish', 'educationCluster'])->latest()->limit(12)->get(),
            'totals' => [
                'parishes' => $parishes->count(),
                'schools' => $parishes->sum('schools_count'),
                'active_assignments' => JurisdictionAssignment::query()->where('is_active', true)->count(),
                'unassigned_parishes' => Parish::query()->whereDoesntHave('jurisdictionAssignments', fn ($query) => $query->where('is_active', true))->count(),
            ],
        ]);
    }

    public function createParish(): View
    {
        return view('diocesan-structure.parishes.create', [
            'title' => 'create parish',
            'breadcrumbs' => [
                ['label' => 'administration'],
                ['label' => 'Parishes', 'url' => route('parishes.index')],
                ['label' => 'create parish'],
            ],
        ]);
    }

    public function storeParish(StoreParishRequest $request): RedirectResponse
    {
        DB::transaction(function () use ($request) {
            Diocese::whereKey($request->validated('diocese_id'))->lockForUpdate()->firstOrFail();
            $sequence = Parish::where('code', 'like', 'MD-P%')->pluck('code')->map(function ($code) {
                return preg_match('/^MD-P([0-9]+)$/', $code, $matches) ? (int) $matches[1] : 0;
            })->max() ?? 0;
            Parish::create([...$request->validated(), 'code' => 'MD-P'.str_pad((string) ($sequence + 1), 4, '0', STR_PAD_LEFT)]);
        });

        return redirect()->route(request()->routeIs('parishes.*') ? 'parishes.index' : 'diocesan-structure.index')->with('status', 'parish created.');
    }

    public function editParish(Parish $parish): View
    {
        return view('diocesan-structure.parishes.edit', [
            'title' => 'edit parish',
            'breadcrumbs' => [
                ['label' => 'administration'],
                ['label' => 'Parishes', 'url' => route('parishes.index')],
                ['label' => $parish->name],
            ],
            'parish' => $parish,
        ]);
    }

    public function updateParish(UpdateParishRequest $request, Parish $parish): RedirectResponse
    {
        $parish->update($request->validated());

        return redirect()->route(request()->routeIs('parishes.*') ? 'parishes.index' : 'diocesan-structure.index')->with('status', 'parish updated.');
    }

    public function createAssignment(): View
    {
        return view('diocesan-structure.assignments.create', [
            'title' => 'assign jurisdiction',
            'breadcrumbs' => [
                ['label' => 'administration'],
                ['label' => 'Parishes', 'url' => route('parishes.index')],
                ['label' => 'assign jurisdiction'],
            ],
            'users' => User::query()->orderBy('name')->get(),
            'parishes' => Parish::query()->orderBy('name')->get(),
            'clusters' => EducationCluster::query()->orderBy('name')->get(),
            'types' => [
                JurisdictionAssignment::TYPE_PARISH_COORDINATOR => 'parish coordinator',
                JurisdictionAssignment::TYPE_INSPECTOR => 'inspector',
            ],
        ]);
    }

    public function storeAssignment(StoreJurisdictionAssignmentRequest $request): RedirectResponse
    {
        JurisdictionAssignment::query()->create($request->validated());

        return redirect()->route('diocesan-structure.index')->with('status', 'jurisdiction assignment created.');
    }
}
