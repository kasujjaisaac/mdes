<?php

namespace App\Http\Controllers\App;

use App\Actions\RecordAuditEvent;
use App\Actions\StartSchoolReturn;
use App\Actions\TransitionSchoolReturn;
use App\Http\Controllers\Controller;
use App\Models\AuditEvent;
use App\Models\NotificationRecord;
use App\Models\Parish;
use App\Models\ReportingPeriod;
use App\Models\School;
use App\Models\SchoolReturn;
use App\Models\User;
use App\Support\Navigation;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class SchoolReturnController extends Controller
{
    public function index(Request $request): View
    {
        $request->validate([
            'period_id' => ['nullable', 'integer'],
            'status' => ['nullable', 'in:not_started,draft,submitted,certified,returned_for_correction,approved,locked,pending_review'],
            'search' => ['nullable', 'string', 'max:200'],
        ]);
        if (! $request->user()->isSchoolRepresentative()) {
            return $this->reviewQueue($request);
        }
        $scope = SchoolReturn::query()
            ->whereHas('reportingPeriod', fn ($q) => $q->where('is_archived', false))
            ->whereHas('school', fn ($q) => $q->visibleTo($request->user()));
        $counts = (clone $scope)->selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status');
        $returns = SchoolReturn::query()
            ->with(['school.parish', 'reportingPeriod'])
            ->whereHas('reportingPeriod', fn ($q) => $q->where('is_archived', false))
            ->whereHas('school', fn ($query) => $query->visibleTo($request->user()))
            ->when($request->filled('search'), fn ($q) => $q->whereHas('school', fn ($s) => $s->where('official_name', 'like', '%'.$request->string('search')->toString().'%')))
            ->when($request->filled('period_id'), fn ($q) => $q->where('reporting_period_id', $request->integer('period_id')))
            ->when($request->filled('status'), fn ($q) => in_array($request->input('status'), [...SchoolReturn::PENDING_REVIEW_STATUSES, 'pending_review']) ? $q->whereIn('status', SchoolReturn::PENDING_REVIEW_STATUSES) : $q->where('status', $request->input('status')))
            ->latest('updated_at')
            ->paginate(15)->withQueryString();

        return view('returns.index', [
            'title' => Navigation::submissionLabel(),
            'breadcrumbs' => [['label' => 'school statistics'], ['label' => Navigation::submissionLabel()]],
            'returns' => $returns,
            'submissionCounts' => $counts,
            'periods' => ReportingPeriod::query()->where('is_archived', false)->whereHas('schools', fn ($q) => $q->visibleTo($request->user()))->latest()->get(),
            'schools' => School::query()->visibleTo($request->user())->orderBy('official_name')->get(),
        ]);
    }

    private function reviewQueue(Request $request): View
    {
        $request->validate(['year' => ['nullable', 'integer', 'min:2000', 'max:2100'], 'parish_id' => ['nullable', 'integer', 'exists:parishes,id'], 'level' => ['nullable', 'in:nursery,primary,secondary,vocational_technical,tertiary']]);
        $schools = School::visibleTo($request->user());
        $scope = SchoolReturn::with(['school.parish', 'reportingPeriod'])
            ->whereNotNull('submitted_at')->whereIn('status', [...SchoolReturn::SUBMITTED_STATUSES, SchoolReturn::STATUS_RETURNED_FOR_CORRECTION])
            ->whereIn('school_id', (clone $schools)->select('id'))
            ->when($request->filled('search'), fn ($q) => $q->whereHas('school', fn ($s) => $s->where('official_name', 'like', '%'.$request->string('search')->toString().'%')))
            ->when($request->filled('year'), fn ($q) => $q->whereHas('reportingPeriod', fn ($p) => $p->where('year', $request->integer('year'))))
            ->when($request->filled('period_id'), fn ($q) => $q->where('reporting_period_id', $request->integer('period_id')))
            ->when($request->filled('parish_id'), fn ($q) => $q->whereHas('school', fn ($s) => $s->where('parish_id', $request->integer('parish_id'))))
            ->when($request->filled('level'), fn ($q) => $q->whereHas('school', fn ($s) => $s->where('school_level', $request->input('level'))));
        $counts = (clone $scope)->selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status');
        $returns = $scope->when($request->filled('status'), fn ($q) => in_array($request->input('status'), [...SchoolReturn::PENDING_REVIEW_STATUSES, 'pending_review']) ? $q->whereIn('status', SchoolReturn::PENDING_REVIEW_STATUSES) : $q->where('status', $request->input('status')))
            ->latest('submitted_at')->orderByDesc('id')->paginate(20)->withQueryString();

        return view('returns.admin-index', [
            'title' => 'School Submissions', 'returns' => $returns, 'submissionCounts' => $counts,
            'years' => ReportingPeriod::orderByDesc('year')->distinct()->pluck('year'),
            'parishes' => Parish::whereIn('id', (clone $schools)->select('parish_id'))->orderBy('name')->get(),
        ]);
    }

    public function start(Request $request, ReportingPeriod $reportingPeriod, School $school, StartSchoolReturn $start): RedirectResponse
    {
        abort_unless($request->user()->isSchoolRepresentative(), 403);
        Gate::authorize('view', $school);

        try {
            $return = $start->handle($reportingPeriod, $school, $request->user());
        } catch (DomainException $exception) {
            return back()->withErrors(['return' => $exception->getMessage()]);
        }

        return redirect()->route($request->user()->isSchoolRepresentative() ? 'representative.confirm' : 'returns.show', $return);
    }

    public function show(SchoolReturn $return): View|RedirectResponse
    {
        Gate::authorize('view', $return);

        if (auth()->user()->isSchoolRepresentative()) {
            if (in_array($return->status, ['draft', 'returned_for_correction'])) {
                return redirect()->route('returns.form.show', $return);
            }

            return view('returns.submitted', ['title' => 'Submitted school data', 'return' => $return->load('school', 'reportingPeriod')]);
        }

        if ($return->submitted_at) {
            return view('returns.submitted', [
                'title' => 'Submission for '.$return->school->official_name,
                'return' => $return->load(['school', 'reportingPeriod']),
                'adminReview' => true,
                'reviewHistory' => AuditEvent::where('auditable_type', SchoolReturn::class)->where('auditable_id', $return->id)->whereIn('action', ['submission.submit', 'submission.approve', 'submission.return_for_correction'])->with('user')->oldest('id')->get(),
            ]);
        }

        return view('returns.show', [
            'title' => 'Submission for '.$return->school->official_name,
            'breadcrumbs' => [['label' => 'school statistics'], ['label' => Navigation::submissionLabel(), 'url' => route('returns.index')], ['label' => $return->school->official_name]],
            'return' => $return->load(['school', 'reportingPeriod', 'sections', 'enrolmentStatistics', 'attendanceWelfareStatistic', 'staffStatistic']),
            'reviewHistory' => AuditEvent::where('auditable_type', SchoolReturn::class)->where('auditable_id', $return->id)->whereIn('action', ['submission.submit', 'submission.approve', 'submission.return_for_correction'])->with('user')->oldest('id')->get(),
        ]);
    }

    public function transition(Request $request, SchoolReturn $return, TransitionSchoolReturn $transition): RedirectResponse
    {
        Gate::authorize(in_array($request->input('action'), ['certify', 'approve', 'lock', 'return_for_correction']) ? 'review' : 'update', $return);

        if ($request->input('action') === 'submit' && $request->user()->isSchoolRepresentative()) {
            $request->validate(['acknowledged' => ['accepted']]);
        }

        $request->validate(['action' => ['required', 'in:submit,approve,return_for_correction'], 'reason' => ['required_if:action,return_for_correction', 'nullable', 'string', 'max:3000']]);
        try {
            DB::transaction(function () use ($request, $return, $transition) {
                School::whereKey($return->school_id)->lockForUpdate()->firstOrFail();
                $locked = SchoolReturn::whereKey($return->id)->lockForUpdate()->firstOrFail();
                Gate::authorize(in_array($request->input('action'), ['certify', 'approve', 'lock', 'return_for_correction']) ? 'review' : 'update', $locked);
                $oldStatus = $locked->status;
                match ($request->input('action')) {
                    'submit' => $transition->submit($locked, $request->user()),
                    'approve' => $transition->approve($locked, $request->user()),
                    'return_for_correction' => $transition->returnForCorrection($locked, $request->user(), $request->string('reason')->toString()),
                };
                if (in_array($request->input('action'), ['approve', 'return_for_correction'])) {
                    $recipient = User::whereKey($locked->submitted_by)->where('school_id', $locked->school_id)->first()
                        ?? User::where('school_id', $locked->school_id)->where('status', 'active')->first();
                    if ($recipient) {
                        NotificationRecord::create(['user_id' => $recipient->id, 'sender_id' => $request->user()->id, 'school_id' => $locked->school_id, 'school_return_id' => $locked->id, 'type' => 'school_feedback', 'severity' => 'info', 'subject' => $request->input('action') === 'approve' ? 'Submission approved' : 'Submission returned for correction', 'message' => $request->input('action') === 'approve' ? 'Your school submission has been approved.' : $request->string('reason')->toString(), 'status' => 'open']);
                    }
                }
                app(RecordAuditEvent::class)->handle($request, 'submission.'.$request->input('action'), $locked, ['status' => $oldStatus], ['status' => $locked->fresh()->status], $request->input('reason'));
            });
        } catch (DomainException $exception) {
            return back()->withErrors(['return' => $exception->getMessage()]);
        }

        return redirect()->route('returns.show', $return)->with('status', 'return updated.');
    }
}
