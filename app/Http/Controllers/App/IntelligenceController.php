<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\AuditEvent;
use App\Models\DataQualityFinding;
use App\Models\ProductionReadinessReview;
use App\Models\ReportExport;
use App\Models\School;
use App\Models\SchoolReturn;
use App\Services\ProductionReadinessService;
use App\Services\ReportExportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class IntelligenceController extends Controller
{
    public function reports(): View
    {
        Gate::authorize('reports.view');

        return view('intelligence.reports', [
            'title' => 'reports',
            'schools' => School::query()->visibleTo(auth()->user())->count(),
            'lockedReturns' => SchoolReturn::query()->whereHas('school', fn ($q) => $q->visibleTo(auth()->user()))->where('status', SchoolReturn::STATUS_LOCKED)->count(),
            'exports' => ReportExport::query()->where('generated_by', auth()->id())->whereIn('report_type', ['school registry', 'enrolment', 'staff', 'examinations', 'infrastructure', 'religious affiliation', 'utilities', 'staff study programmes'])->latest()->limit(10)->get(),
        ]);
    }

    public function export(Request $request, ReportExportService $service): RedirectResponse
    {
        Gate::authorize('reports.export');
        $request->validate(['report_type' => ['required', 'in:school registry,enrolment,staff,examinations,infrastructure,religious affiliation,utilities,staff study programmes'], 'format' => ['required', 'in:csv,xlsx,pdf']]);
        try {
            $service->generate($request->user(), $request->string('report_type')->toString(), $request->string('format')->toString());
        } catch (ValidationException $error) {
            throw $error;
        } catch (\Throwable $error) {
            report($error);

            return back()->withErrors(['export' => 'Unable to generate the report. Please try again or contact support.']);
        }

        return redirect()->route('reports.index')->with('status', 'Report generated. Download it from Recent exports.');
    }

    public function download(ReportExport $export)
    {
        Gate::authorize('reports.export');
        abort_unless($export->generated_by === auth()->id(), 403);
        $ids = $export->filters['_school_ids'] ?? [];
        abort_unless(School::visibleTo(auth()->user())->whereIn('id', $ids)->count() === count($ids), 403);
        abort_unless($export->status === 'generated' && $export->disk === 'local' && $export->path && Storage::disk('local')->exists($export->path), 404);

        return Storage::disk('local')->download($export->path, str_replace(' ', '-', $export->report_type).'-'.$export->id.'.'.$export->format);
    }

    public function analytics(): View
    {
        Gate::authorize('reports.view');

        return view('intelligence.analytics', [
            'title' => 'analytics',
            'returnsByStatus' => SchoolReturn::query()->whereHas('school', fn ($q) => $q->visibleTo(auth()->user()))->selectRaw('status, count(*) as aggregate')->groupBy('status')->pluck('aggregate', 'status'),
        ]);
    }

    public function quality(): View
    {
        Gate::authorize('reports.view');

        return view('operations.index', [
            'title' => 'data quality',
            'records' => DataQualityFinding::query()->whereHas('schoolReturn', fn ($q) => $q->whereNotNull('statistical_form')->whereHas('school', fn ($schools) => $schools->visibleTo(auth()->user())))->latest()->paginate(20),
            'headers' => ['section', 'severity', 'rule', 'message'],
            'rows' => fn (DataQualityFinding $finding): array => [$finding->section, $finding->severity, $finding->rule, str($finding->message)->limit(80)],
            'createRoute' => null,
        ]);
    }

    public function audit(): View
    {
        Gate::authorize('audit.view');

        return view('operations.index', [
            'title' => 'audit trail',
            'records' => AuditEvent::query()->latest()->paginate(20),
            'headers' => ['action', 'entity', 'reason', 'ip'],
            'rows' => fn (AuditEvent $event): array => [$event->action, class_basename($event->auditable_type).' #'.$event->auditable_id, $event->reason ?? '-', $event->ip_address ?? '-'],
            'createRoute' => null,
        ]);
    }

    public function readiness(Request $request, ProductionReadinessService $service): View
    {
        Gate::authorize('reports.view');

        return view('intelligence.readiness', [
            'title' => 'production readiness',
            'review' => new ProductionReadinessReview($service->snapshotPayload()),
        ]);
    }
}
