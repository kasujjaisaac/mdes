<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Http\Requests\SaveAcademicPerformanceRequest;
use App\Http\Requests\SaveAttendanceWelfareRequest;
use App\Http\Requests\SaveCatholicIdentityRequest;
use App\Http\Requests\SaveEnrolmentRequest;
use App\Http\Requests\SaveGovernanceComplianceRequest;
use App\Http\Requests\SaveInfrastructureRequest;
use App\Http\Requests\SaveStaffStatisticsRequest;
use App\Models\ExaminationType;
use App\Models\SchoolReturn;
use App\Services\AcademicPerformanceSectionService;
use App\Services\AttendanceWelfareSectionService;
use App\Services\CatholicIdentitySectionService;
use App\Services\EnrolmentSectionService;
use App\Services\GovernanceComplianceSectionService;
use App\Services\InfrastructureSectionService;
use App\Services\StaffStatisticsSectionService;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class ReturnSectionController extends Controller
{
    public function editEnrolment(SchoolReturn $return): View|RedirectResponse
    {
        Gate::authorize('update', $return);
        if (isset($return->statistical_form['enrolment'])) return redirect()->route('returns.form.show', [$return, 'enrolment']);


        return view('returns.sections.enrolment', ['title' => 'enrolment', 'return' => $return->load('enrolmentStatistics')]);
    }

    public function saveEnrolment(SaveEnrolmentRequest $request, SchoolReturn $return, EnrolmentSectionService $service): RedirectResponse
    {
        Gate::authorize('update', $return);
        if (isset($return->statistical_form['enrolment'])) return redirect()->route('returns.form.show', [$return, 'enrolment']);


        try {
            $service->save($return, $request->validated('rows'));
        } catch (DomainException $exception) {
            return back()->withInput()->withErrors(['section' => $exception->getMessage()]);
        }

        return redirect()->route('returns.show', $return)->with('status', 'enrolment saved.');
    }

    public function editAttendance(SchoolReturn $return): View
    {
        Gate::authorize('update', $return);

        return view('returns.sections.attendance', ['title' => 'attendance and welfare', 'return' => $return->load('attendanceWelfareStatistic')]);
    }

    public function saveAttendance(SaveAttendanceWelfareRequest $request, SchoolReturn $return, AttendanceWelfareSectionService $service): RedirectResponse
    {
        Gate::authorize('update', $return);

        try {
            $service->save($return, $request->validated());
        } catch (DomainException $exception) {
            return back()->withInput()->withErrors(['section' => $exception->getMessage()]);
        }

        return redirect()->route('returns.show', $return)->with('status', 'attendance and welfare saved.');
    }

    public function editStaff(SchoolReturn $return): View|RedirectResponse
    {
        Gate::authorize('update', $return);
        if (isset($return->statistical_form['teaching'])) return redirect()->route('returns.form.show', [$return, 'teaching']);


        return view('returns.sections.staff', ['title' => 'staff statistics', 'return' => $return->load('staffStatistic')]);
    }

    public function saveStaff(SaveStaffStatisticsRequest $request, SchoolReturn $return, StaffStatisticsSectionService $service): RedirectResponse
    {
        Gate::authorize('update', $return);
        if (isset($return->statistical_form['teaching'])) return redirect()->route('returns.form.show', [$return, 'teaching']);


        try {
            $service->save($return, $request->validated());
        } catch (DomainException $exception) {
            return back()->withInput()->withErrors(['section' => $exception->getMessage()]);
        }

        return redirect()->route('returns.show', $return)->with('status', 'staff statistics saved.');
    }

    public function editAcademic(SchoolReturn $return): View
    {
        Gate::authorize('update', $return);

        return view('returns.sections.academic', [
            'title' => 'academic performance',
            'return' => $return->load('academicPerformanceStatistics.examinationType'),
            'examinationTypes' => ExaminationType::query()->with('gradingScales')->where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function saveAcademic(SaveAcademicPerformanceRequest $request, SchoolReturn $return, AcademicPerformanceSectionService $service): RedirectResponse
    {
        Gate::authorize('update', $return);

        try {
            $service->save($return, $request->validated('rows'));
        } catch (DomainException $exception) {
            return back()->withInput()->withErrors(['section' => $exception->getMessage()]);
        }

        return redirect()->route('returns.show', $return)->with('status', 'academic performance saved.');
    }

    public function editInfrastructure(SchoolReturn $return): View
    {
        Gate::authorize('update', $return);

        return view('returns.sections.infrastructure', ['title' => 'infrastructure', 'return' => $return->load('infrastructureStatistics')]);
    }

    public function saveInfrastructure(SaveInfrastructureRequest $request, SchoolReturn $return, InfrastructureSectionService $service): RedirectResponse
    {
        Gate::authorize('update', $return);

        try {
            $service->save($return, $request->validated('rows'));
        } catch (DomainException $exception) {
            return back()->withInput()->withErrors(['section' => $exception->getMessage()]);
        }

        return redirect()->route('returns.show', $return)->with('status', 'infrastructure saved.');
    }

    public function editCatholicIdentity(SchoolReturn $return): View
    {
        Gate::authorize('update', $return);

        return view('returns.sections.catholic-identity', ['title' => 'catholic identity', 'return' => $return->load('catholicIdentityStatistic')]);
    }

    public function saveCatholicIdentity(SaveCatholicIdentityRequest $request, SchoolReturn $return, CatholicIdentitySectionService $service): RedirectResponse
    {
        Gate::authorize('update', $return);

        try {
            $service->save($return, $request->validated());
        } catch (DomainException $exception) {
            return back()->withInput()->withErrors(['section' => $exception->getMessage()]);
        }

        return redirect()->route('returns.show', $return)->with('status', 'catholic identity saved.');
    }

    public function editGovernance(SchoolReturn $return): View
    {
        Gate::authorize('update', $return);

        return view('returns.sections.governance', ['title' => 'governance and compliance', 'return' => $return->load('governanceComplianceStatistic')]);
    }

    public function saveGovernance(SaveGovernanceComplianceRequest $request, SchoolReturn $return, GovernanceComplianceSectionService $service): RedirectResponse
    {
        Gate::authorize('update', $return);

        try {
            $service->save($return, $request->validated());
        } catch (DomainException $exception) {
            return back()->withInput()->withErrors(['section' => $exception->getMessage()]);
        }

        return redirect()->route('returns.show', $return)->with('status', 'governance and compliance saved.');
    }
}
