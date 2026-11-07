<?php

namespace App\Http\Controllers\App;

use App\Actions\RecordAuditEvent;
use App\Actions\StartSchoolReturn;
use App\Actions\TransitionSchoolReturn;
use App\Http\Controllers\Controller;
use App\Models\AuditEvent;
use App\Models\Parish;
use App\Models\ReportingPeriod;
use App\Models\School;
use App\Models\SchoolReturn;
use App\Services\SubmissionNotificationService;
use App\Services\SubmissionVersionService;
use App\Support\Navigation;
use App\Support\StatisticalForm;
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
            'startablePeriods' => ReportingPeriod::query()->where('is_archived', false)
                ->whereHas('schools', fn ($query) => $query->where('schools.id', $request->user()->school_id))
                ->whereDoesntHave('schoolReturns', fn ($query) => $query->where('school_id', $request->user()->school_id))
                ->when($request->filled('period_id'), fn ($query) => $query->whereKey($request->integer('period_id')))
                ->orderByDesc('year')->get()->filter(fn ($period) => $period->isSubmissionOpen() && (! $request->filled('status') || $request->input('status') === 'not_started')),
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

        return redirect()->route('returns.form.introduction', $return);
    }

    public function show(SchoolReturn $return): View|RedirectResponse
    {
        Gate::authorize('view', $return);

        if (auth()->user()->isSchoolRepresentative()) {
            if ($return->status === SchoolReturn::STATUS_NOT_STARTED) {
                return redirect()->route('returns.index')->with('status', 'Choose Start form to begin this return.');
            }
            if (in_array($return->status, ['draft', 'returned_for_correction'])) {
                return redirect()->route('returns.form.show', $return);
            }

            return view('returns.submitted', ['title' => 'Submitted school data', 'return' => $return->load('school', 'reportingPeriod')]);
        }

        if ($return->submitted_at) {
            $reviewData = DB::transaction(function () use ($return) {
                $locked = SchoolReturn::whereKey($return->id)->lockForUpdate()->firstOrFail();
                Gate::authorize('view', $locked);

                return ['return' => app(SubmissionVersionService::class)->forDisplay($locked->load(['school', 'reportingPeriod'])), 'reviewToken' => StatisticalForm::adminReviewFingerprint($locked)];
            });

            return view('returns.submitted', [
                'title' => 'Submission for '.$return->school->official_name,
                ...$reviewData,
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
        Gate::authorize(in_array($request->input('action'), ['certify', 'approve', 'lock', 'return_for_correction']) ? 'review' : ($request->input('action') === 'submit' && $return->isLocked() ? 'view' : 'update'), $return);

        if ($request->input('action') === 'submit' && $request->user()->isSchoolRepresentative()) {
            $request->validate(['acknowledged' => ['accepted'], 'review_fingerprint' => ['required', 'string', 'size:64']]);
        }

        if (in_array($request->input('action'), ['approve', 'return_for_correction'], true)) {
            $request->validate(['review_token' => ['required', 'string', 'size:64']]);
        }
        $request->validate(['action' => ['required', 'in:submit,approve,return_for_correction'], 'reason' => ['required_if:action,return_for_correction', 'nullable', 'string', 'max:3000']]);
        $alreadySubmitted = false;
        try {
            DB::transaction(function () use ($request, $return, $transition, &$alreadySubmitted) {
                School::whereKey($return->school_id)->lockForUpdate()->firstOrFail();
                $locked = SchoolReturn::whereKey($return->id)->lockForUpdate()->firstOrFail();
                if ($request->input('action') === 'submit' && $request->user()->isSchoolRepresentative() && $locked->school_id === $request->user()->school_id && $locked->isLocked() && $locked->submitted_at) {
                    Gate::authorize('view', $locked);
                    $alreadySubmitted = true;

                    return;
                }
                Gate::authorize(in_array($request->input('action'), ['certify', 'approve', 'lock', 'return_for_correction']) ? 'review' : 'update', $locked);
                if ($request->input('action') === 'submit' && $request->user()->isSchoolRepresentative()
                    && ! hash_equals(StatisticalForm::reviewFingerprint($locked), $request->string('review_fingerprint')->toString())) {
                    throw new DomainException('This form changed after you opened the review. Review the updated answers and acknowledge them again before submitting.');
                }
                if (in_array($request->input('action'), ['approve', 'return_for_correction'], true) && ! hash_equals(StatisticalForm::adminReviewFingerprint($locked), $request->string('review_token')->toString())) {
                    throw new DomainException('This submission changed after you opened it. Open the latest submitted version before approving or returning it.');
                }
                $oldStatus = $locked->status;
                $updated = match ($request->input('action')) {
                    'submit' => $transition->submit($locked, $request->user()),
                    'approve' => $transition->approve($locked, $request->user()),
                    'return_for_correction' => $transition->returnForCorrection($locked, $request->user(), $request->string('reason')->toString()),
                };
                app(SubmissionNotificationService::class)->send($updated, $request->user(), $request->input('action'), $request->input('reason'), $oldStatus === SchoolReturn::STATUS_RETURNED_FOR_CORRECTION);
                app(RecordAuditEvent::class)->handle($request, 'submission.'.$request->input('action'), $locked, ['status' => $oldStatus], ['status' => $locked->fresh()->status], $request->input('reason'));
            });
        } catch (DomainException $exception) {
            $response = back()->withErrors(['return' => $exception->getMessage()]);
            if ($request->input('action') === 'submit') {
                $response->with('submission_checks_for', $return->id);
            }

            return $response;
        }

        return redirect()->route('returns.show', $return)->with('status', $alreadySubmitted ? 'Your form has already been received. Reference: MDES-'.$return->id.'.' : ($request->input('action') === 'submit' ? 'Your form has been submitted successfully. Reference: MDES-'.$return->id.'.' : 'Return updated.'));
    }
}
