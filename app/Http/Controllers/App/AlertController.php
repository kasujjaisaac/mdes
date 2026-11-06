<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\NotificationRecord;
use App\Models\School;
use App\Models\SchoolReturn;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class AlertController extends Controller
{
    public function composeSchool(Request $request, School $school)
    {
        abort_unless(! $request->user()->isSchoolRepresentative() && $request->user()->hasPermission('returns.review'), 403);
        Gate::authorize('view', $school);

        return view('alerts.message-school', ['title' => 'Message school', 'school' => $school->load('parish')]);
    }

    public function storeSchool(Request $request, School $school)
    {
        $request->merge(['school_id' => $school->id, 'school_return_id' => null]);

        return $this->store($request);
    }

    public function index(Request $request)
    {
        $user = $request->user();
        $canSend = ! $user->isSchoolRepresentative() && $user->hasPermission('returns.review');
        $selectedSchoolId = null;
        if ($canSend) {
            $request->validate(['school_id' => ['nullable', 'integer', 'exists:schools,id'], 'return_id' => ['nullable', 'integer', 'exists:school_returns,id']]);
            if ($request->filled('school_id')) {
                $school = School::findOrFail($request->integer('school_id'));
                Gate::authorize('view', $school);
                $selectedSchoolId = $school->id;
            } elseif ($request->filled('return_id')) {
                $selectedReturn = SchoolReturn::findOrFail($request->integer('return_id'));
                Gate::authorize('review', $selectedReturn);
                $selectedSchoolId = $selectedReturn->school_id;
            }
        }
        $query = NotificationRecord::query()->where(function ($q) use ($user, $canSend) {
            $q->where('user_id', $user->id);
            if ($canSend) {
                $q->orWhere(fn ($sent) => $sent->where('sender_id', $user->id)->where('type', '!=', 'announcement'));
            }
        })->with(['sender', 'school', 'schoolReturn.reportingPeriod']);
        if ($user->isSchoolRepresentative()) {
            $query->where(fn ($q) => $q->whereNull('school_id')->orWhere('school_id', $user->school_id ?? -1));
        }
        $messageCount = (clone $query)->count();
        $unreadCount = (clone $query)->where('user_id', $user->id)->whereNull('read_at')->count();
        if ($request->input('filter') === 'unread') {
            $query->where('user_id', $user->id)->whereNull('read_at');
        }

        return view('alerts.index', ['title' => 'Announcements', 'messageCount' => $messageCount, 'unreadCount' => $unreadCount, 'alerts' => $query->latest()->paginate(15)->withQueryString(), 'canSend' => $canSend, 'selectedSchoolId' => $selectedSchoolId]);
    }

    public function store(Request $request)
    {
        abort_unless(! $request->user()->isSchoolRepresentative() && $request->user()->hasPermission('returns.review'), 403);
        $data = $request->validate(['school_id' => ['required_without:school_return_id', 'nullable', 'integer', 'exists:schools,id'], 'school_return_id' => ['nullable', 'integer', 'exists:school_returns,id'], 'subject' => ['required', 'string', 'max:180'], 'message' => ['required', 'string', 'max:5000']]);
        $return = isset($data['school_return_id']) ? SchoolReturn::findOrFail($data['school_return_id']) : null;
        $school = School::findOrFail($data['school_id'] ?? $return->school_id);
        Gate::authorize('view', $school);
        if ($return) {
            Gate::authorize('review', $return);
            if ($return->school_id !== $school->id) {
                throw ValidationException::withMessages(['school_id' => 'The selected submission belongs to another school.']);
            }
            abort_unless($return->submitted_at, 422, 'Select a submitted form.');
        }
        $recipients = User::where('school_id', $school->id)->where('status', 'active')->with('roles.permissions')->get()->filter(fn ($u) => $u->isSchoolRepresentative());
        if ($recipients->isEmpty()) {
            return back()->withErrors(['school_id' => 'This school has no active representative account.'])->withInput();
        }
        DB::transaction(function () use ($recipients, $return, $school, $data, $request) {
            foreach ($recipients as $recipient) {
                NotificationRecord::create(['user_id' => $recipient->id, 'sender_id' => $request->user()->id, 'school_id' => $school->id, 'school_return_id' => $return?->id, 'subject' => $data['subject'], 'message' => $data['message'], 'type' => $return ? 'school_feedback' : 'school_message', 'severity' => 'info', 'status' => 'open']);
            }
        });

        return redirect()->route('alerts.index')->with('status', 'Message sent to the school representative accounts.');
    }

    public function read(Request $request, NotificationRecord $alert)
    {
        abort_unless($alert->user_id === $request->user()->id, 403);
        if ($request->user()->isSchoolRepresentative() && $alert->school_id) {
            abort_unless($alert->school_id === $request->user()->school_id, 403);
        }
        if ($alert->type === 'announcement' && $alert->announcement_id) {
            abort_unless($request->user()->isSchoolRepresentative() && $alert->school_id === $request->user()->school_id, 403);
            // Acknowledgement belongs to the school, including its other representative accounts.
            NotificationRecord::where('announcement_id', $alert->announcement_id)
                ->where('school_id', $alert->school_id)->whereNull('read_at')
                ->update(['read_at' => now(), 'status' => 'read']);

            return back()->with('status', 'Announcement acknowledged. Thank you.');
        }
        if (! $alert->read_at) {
            $alert->update(['read_at' => now(), 'status' => 'read']);
        }

        return back()->with('status', 'Message marked as read.');
    }
}
