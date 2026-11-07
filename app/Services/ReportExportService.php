<?php

namespace App\Services;

use App\Models\ReportExport;
use App\Models\User;
use App\Support\Reports\ReportCatalog;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ReportExportService
{
    public function generate(User $user, string $reportType, string $format, array $filters = []): ReportExport
    {
        abort_unless($user->isAdministrator() && $user->hasPermission('reports.export'), 403);
        abort_unless(isset(ReportCatalog::TYPES[$reportType],ReportCatalog::FORMATS[$format]), 422);
        $report = app(ReportDataService::class)->build($user, $reportType, $filters);
        $export = ReportExport::create(['generated_by' => $user->id, 'report_type' => $reportType, 'format' => $format,
            'filters' => [...$filters, '_school_ids' => $report['school_ids'], '_metadata' => $report['metadata']], 'status' => 'generating', 'generated_at' => now()]);
        $path = 'report-exports/'.Str::uuid().'.'.$format;
        try {
            $bytes = app(ReportRenderer::class)->render($report, $format);
            if (! Storage::disk('local')->put($path, $bytes)) {
                throw new \RuntimeException('Unable to store report.');
            }
            $export->update(['status' => 'generated', 'disk' => 'local', 'path' => $path]);

            return $export;
        } catch (\Throwable $error) {
            Storage::disk('local')->delete($path);
            $export->update(['status' => 'failed', 'failure_reason' => 'Report generation failed. Narrow the filters or try Excel.']);
            throw $error;
        }
    }
}
