<?php

namespace App\Services;

use App\Models\ReportExport;
use App\Models\School;
use App\Models\SchoolReturn;
use App\Models\User;
use Dompdf\Dompdf;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use RuntimeException;
use Throwable;

class ReportExportService
{
    private const SECTIONS = ['enrolment' => ['enrolment'], 'staff' => ['teaching', 'support'], 'examinations' => ['academic'], 'infrastructure' => ['facilities'], 'religious affiliation' => ['religion'], 'utilities' => ['utilities'], 'staff study programmes' => ['studies']];

    public function generate(User $user, string $reportType, string $format, array $filters = []): ReportExport
    {
        abort_unless($user->hasPermission('reports.export'), 403);
        if (! in_array($format, ['csv', 'xlsx', 'pdf'], true) || ($reportType !== 'school registry' && ! isset(self::SECTIONS[$reportType]))) {
            abort(422);
        }
        $schoolIds = School::visibleTo($user)->pluck('id')->all();
        $export = ReportExport::create(['generated_by' => $user->id, 'report_type' => $reportType, 'format' => $format, 'filters' => [...$filters, '_school_ids' => $schoolIds], 'status' => 'generating', 'generated_at' => now()]);
        $path = 'report-exports/'.Str::uuid().'.'.$format;
        try {
            [$headers, $rows] = $this->rows($user, $reportType, $filters);
            $bytes = $this->render($format, $reportType, $headers, $rows);
            if (! Storage::disk('local')->put($path, $bytes)) {
                throw new RuntimeException('Unable to store report.');
            }
            $export->update(['status' => 'generated', 'disk' => 'local', 'path' => $path, 'generated_at' => now(), 'failure_reason' => null]);

            return $export;
        } catch (Throwable $error) {
            Storage::disk('local')->delete($path);
            $export->update(['status' => 'failed', 'failure_reason' => 'Report generation failed. Please try again or contact support.']);
            throw $error;
        }
    }

    private function rows(User $user, string $type, array $filters): array
    {
        if ($type === 'school registry') {
            $rows = School::visibleTo($user)->with('parish', 'district')->orderBy('official_name')->get()->map(fn ($s) => [$s->official_name, $s->diocesan_code, $s->parish?->name, $s->district?->name, $s->school_level, $s->operational_status])->all();

            return [['School', 'Diocesan code', 'Parish', 'District', 'Level', 'Status'], $rows];
        }
        $rows = [];
        $returns = SchoolReturn::where('status', 'locked')->whereHas('school', fn ($q) => $q->visibleTo($user))->whereHas('reportingPeriod', fn ($q) => $q->where('is_archived', false)->when(isset($filters['year']), fn ($q) => $q->where('year', $filters['year'])))->with('school', 'reportingPeriod')->get();
        foreach ($returns as $return) {
            foreach (self::SECTIONS[$type] as $section) {
                foreach ($this->flatten($return->statistical_form[$section] ?? []) as $field => $value) {
                    $rows[] = [$return->school->official_name, $return->reportingPeriod->year, $section, $field, $value];
                }
            }
        }

        return [['School', 'Reporting year', 'Section', 'Field', 'Value'], $rows];
    }

    private function flatten(array $values, string $prefix = ''): array
    {
        $result = [];
        foreach ($values as $key => $value) {
            $field = $prefix === '' ? (string) $key : $prefix.'.'.$key;
            if (is_array($value)) {
                $result += $this->flatten($value, $field);
            } else {
                $result[$field] = $value;
            }
        }

        return $result;
    }

    private function render(string $format, string $title, array $headers, array $rows): string
    {
        if ($format === 'csv') {
            $stream = fopen('php://temp', 'w+');
            try {
                foreach ([$headers, ...$rows] as $row) {
                    fputcsv($stream, array_map(function ($value) {
                        $text = (string) ($value ?? '');

                        return preg_match('/^[\s]*[=+@-]|^[\t\r\n]/u', $text) ? "'".$text : $text;
                    }, $row), ',', '"', '');
                }
                rewind($stream);

                return stream_get_contents($stream);
            } finally {
                fclose($stream);
            }
        }
        if ($format === 'xlsx') {
            $sheet = new Spreadsheet;
            try {
                foreach ([$headers, ...$rows] as $index => $row) {
                    foreach ($row as $column => $value) {
                        $sheet->getActiveSheet()->setCellValueExplicit(Coordinate::stringFromColumnIndex($column + 1).($index + 1), (string) ($value ?? ''), DataType::TYPE_STRING);
                    }
                }
                $sheet->getActiveSheet()->freezePane('A2');
                $sheet->getActiveSheet()->getStyle('1:1')->getFont()->setBold(true);
                $stream = fopen('php://temp', 'w+');
                try {
                    (new Xlsx($sheet))->save($stream);
                    rewind($stream);

                    return stream_get_contents($stream);
                } finally {
                    fclose($stream);
                }
            } finally {
                $sheet->disconnectWorksheets();
            }
        }
        if (count($rows) > 5000) {
            throw ValidationException::withMessages(['format' => 'Use CSV or Excel for reports exceeding 5,000 rows.']);
        }
        $pdf = new Dompdf(['isRemoteEnabled' => false, 'isJavascriptEnabled' => false]);
        $html = '<html><head><meta charset="utf-8"><style>body{font-family:DejaVu Sans;font-size:9px}table{border-collapse:collapse;width:100%;table-layout:fixed}th,td{border:1px solid #ccd6e3;padding:5px;word-wrap:break-word}th{background:#eaf0fa}thead{display:table-header-group}</style></head><body><h1>'.e(ucfirst($title)).'</h1><p>Generated '.e(now()->toIso8601String()).' | Statistical reports contain locked returns only.</p><table><thead><tr>';
        foreach ($headers as $header) {
            $html .= '<th>'.e($header).'</th>';
        }
        $html .= '</tr></thead><tbody>';
        foreach ($rows as $row) {
            $html .= '<tr>';
            foreach ($row as $value) {
                $html .= '<td>'.e((string) ($value ?? '')).'</td>';
            } $html .= '</tr>';
        }
        $pdf->loadHtml($html.'</tbody></table></body></html>');
        $pdf->setPaper('A4', 'landscape');
        $pdf->render();

        return $pdf->output();
    }
}
