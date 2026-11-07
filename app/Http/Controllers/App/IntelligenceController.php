<?php

namespace App\Http\Controllers\App;

use App\Actions\RecordAuditEvent;
use App\Http\Controllers\Controller;
use App\Models\AuditEvent;
use App\Models\DataQualityFinding;
use App\Models\ProductionReadinessReview;
use App\Models\ReportExport;
use App\Models\ReportingPeriod;
use App\Models\School;
use App\Models\SchoolReturn;
use App\Services\ProductionReadinessService;
use App\Services\ReportDataService;
use App\Services\ReportExportService;
use App\Services\ReportRenderer;
use App\Support\Reports\ReportCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class IntelligenceController extends Controller
{
    public function reports(): View
    {
        Gate::authorize('reports.view');

        return view('intelligence.reports', [
            'title' => 'Reports', 'catalog' => ReportCatalog::TYPES,
            'schools' => School::visibleTo(auth()->user())->with('parish', 'district')->orderBy('official_name')->get(),
            'years' => ReportingPeriod::orderByDesc('year')->distinct()->pluck('year'),
            'exports' => ReportExport::where('generated_by', auth()->id())->latest()->paginate(10),
        ]);
    }

    public function preview(Request $request): View
    {
        Gate::authorize('reports.export');
        $data = $request->validate(ReportCatalog::rules());
        if ($data['report_type'] === 'complete form' && ! $request->filled('school_id')) {
            throw ValidationException::withMessages(['school_id' => 'Select one school for a complete statistical form copy.']);
        }
        $request->flashOnly(array_keys($data));
        $report = app(ReportDataService::class)->build($request->user(), $data['report_type'], $data);

        return view('intelligence.report-preview', ['title' => 'Preview report', 'report' => $report, 'filters' => $data]);
    }

    public function export(Request $request, ReportExportService $service): RedirectResponse
    {
        Gate::authorize('reports.export');
        $data = $request->validate([...ReportCatalog::rules(), 'format' => ['required', Rule::in(array_keys(ReportCatalog::FORMATS))]]);
        if ($data['report_type'] === 'complete form' && ! $request->filled('school_id')) {
            throw ValidationException::withMessages(['school_id' => 'Select one school for a complete statistical form copy.']);
        }
        try {
            $export = $service->generate($request->user(), $data['report_type'], $data['format'], array_diff_key($data, array_flip(['report_type', 'format'])));
            app(RecordAuditEvent::class)->handle($request, 'report.generated', $export, null, ['report_type' => $data['report_type'], 'format' => $data['format'], 'filters' => $export->filters]);
        } catch (ValidationException $error) {
            throw $error;
        } catch (\Throwable $error) {
            report($error);

            return back()->withInput()->withErrors(['export' => 'Unable to generate this report. Please retry with narrower filters.']);
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

        app(RecordAuditEvent::class)->handle(request(), 'report.downloaded', $export, null, ['format' => $export->format]);

        return Storage::disk('local')->download($export->path, str_replace(' ', '-', $export->report_type).'-'.$export->id.'.'.$export->format, ['Content-Type' => ReportRenderer::MIME[$export->format] ?? 'application/octet-stream']);
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
