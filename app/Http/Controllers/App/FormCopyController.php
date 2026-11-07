<?php

namespace App\Http\Controllers\App;

use App\Actions\RecordAuditEvent;
use App\Http\Controllers\Controller;
use App\Models\ReportingPeriod;
use App\Models\School;
use App\Models\SchoolReturn;
use App\Models\SubmissionVersion;
use App\Models\User;
use App\Services\ReportDataService;
use App\Services\ReportRenderer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class FormCopyController extends Controller
{
    public function download(Request $request, SchoolReturn $return)
    {
        Gate::authorize('view', $return);
        $data = $request->validate(['format' => ['nullable', 'in:docx,xlsx,pdf'], 'version' => ['nullable', 'integer', 'min:1'], 'print' => ['sometimes', 'boolean']]);
        $version = isset($data['version']) ? SubmissionVersion::where('school_return_id', $return->id)->whereKey($data['version'])->firstOrFail() : null;
        if (! $version && ($return->isLocked() || $request->user()->isAdministrator())) {
            $version = SubmissionVersion::where('school_return_id', $return->id)->latest('version')->first();
        }
        abort_if($request->user()->isAdministrator() && ! $version && $return->status === 'returned_for_correction', 404, 'The original submitted copy was not preserved for this legacy form.');
        $copy = clone $return;
        $copy->loadMissing('school', 'reportingPeriod');
        if ($version) {
            $payload = $version->payload;
            $copy->statistical_form = $payload['form'] ?? [];
            $copy->profile_snapshot = $payload['profile'] ?? [];
            $copy->status = $version->review_status;
            $copy->submitted_at = $version->submitted_at;
            $copy->setRelation('school', (new School)->forceFill($payload['school']));
            $copy->setRelation('reportingPeriod', (new ReportingPeriod)->forceFill($payload['period']));
        } elseif (in_array($return->status, ['draft', 'returned_for_correction'])) {
            $form = $return->statistical_form ?? [];
            foreach ($return->statistical_form_drafts ?? [] as $key => $draft) {
                if (isset($draft['data'])) {
                    $form[$key] = $draft['data'];
                }
            }
            if (isset($form['enrolment']['community'])) {
                $form['religion']['community'] = $form['enrolment']['community'];
            }
            $form['declaration'] = [];
            $copy->submitted_at = null;
            $copy->statistical_form = $form;
        }
        $format = $data['format'] ?? 'pdf';
        $label = match (true) {
            $version?->review_status === 'approved' => 'Approved copy', $version?->review_status === 'returned_for_correction' => 'Submitted copy returned for correction', $version !== null => 'Submitted copy pending review', $return->isLocked() => 'Saved submitted copy', default => $return->status === 'returned_for_correction' ? 'Returned for correction current draft' : 'Draft copy not submitted'
        };
        $metadata = ['School' => $copy->statistical_form['identity']['school_name'] ?? $copy->school->official_name, 'Registration number' => $copy->profile_snapshot['registration_number'] ?? $copy->school->ministry_registration_number ?? 'Not reported',
            'Reporting year' => (string) $copy->reportingPeriod->year, 'Copy status' => $label, 'Submission reference' => 'MDES-'.$return->id,
            'Version' => $version ? (string) $version->version : 'Current saved progress', 'Submitted by' => $version?->payload['submitter']['name'] ?? ($copy->submitted_at ? (User::find($return->submitted_by)?->name ?? 'Account no longer available') : 'Not submitted'), 'Submitted' => ($copy->submitted_at ? $copy->submitted_at->timezone('Africa/Kampala')->format('d F Y H:i').' EAT' : 'Not submitted'),
            'Generated' => now('Africa/Kampala')->format('d F Y H:i').' EAT'];
        $notes = ['This copy contains the saved answers for the applicable questionnaire sections. Editing a downloaded Word or Excel file does not update the system record.'];
        if ($version?->legacy_import) {
            $notes[] = 'Legacy imported copy: earlier submission versions were not recorded by the system.';
        }
        if (! $version && ! $return->isLocked()) {
            $notes[] = 'This is a draft copy and is not evidence of submission or approval.';
        }
        if ($version?->correction_reason) {
            $notes[] = 'Correction requested: '.$version->correction_reason;
        }
        $intro = $version?->payload['introduction'] ?? json_decode(file_get_contents(resource_path('forms/statistical-2026-introduction.json')), true, 512, JSON_THROW_ON_ERROR);
        $metadata = ['Institution' => $intro['header'], 'Secretariat contact' => $intro['contact'], ...$metadata];
        $tables = [['name' => 'Introduction', 'headers' => ['Item', 'Text'], 'rows' => [['Instructions', $intro['introduction']], ...array_map(fn ($v) => ['Objective', $v], $intro['objectives'])], 'note' => '']];
        $tables = [...$tables, ...app(ReportDataService::class)->formTables($copy, $version?->payload['schema'] ?? null)];
        $report = ['title' => $intro['title'], 'metadata' => $metadata, 'notes' => $notes, 'tables' => $tables];
        $bytes = app(ReportRenderer::class)->render($report, $format);
        app(RecordAuditEvent::class)->handle($request, 'school_form.downloaded', $return, null, ['format' => $format, 'version' => $version?->version, 'copy_status' => $label]);

        return response($bytes, 200, ['Content-Type' => ReportRenderer::MIME[$format], 'Content-Disposition' => (($format === 'pdf' && $request->boolean('print')) ? 'inline' : 'attachment').'; filename="MDES-'.$return->id.'-'.($version ? 'v'.$version->version : 'draft').'.'.$format.'"', 'Cache-Control' => 'no-store, private']);
    }
}
