<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\NotificationRecord;
use App\Models\SchoolReturn;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class AlertController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $canSend = ! $user->isSchoolRepresentative() && $user->hasPermission('returns.review');
        $query = NotificationRecord::query()->where(function ($q) use ($user, $canSend) {
            $q->where('user_id', $user->id);
            if ($canSend) {
                $q->orWhere('sender_id', $user->id);
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

        return view('alerts.index', ['title' => 'Alerts', 'messageCount' => $messageCount, 'unreadCount' => $unreadCount, 'alerts' => $query->latest()->paginate(15)->withQueryString(), 'canSend' => $canSend, 'submissions' => $canSend ? SchoolReturn::whereHas('school', fn ($q) => $q->visibleTo($user))->whereNotNull('submitted_at')->with('school', 'reportingPeriod')->latest('submitted_at')->get() : collect()]);
    }

    public function store(Request $request)
    {
        abort_unless(! $request->user()->isSchoolRepresentative() && $request->user()->hasPermission('returns.review'), 403);
        $data = $request->validate(['school_return_id' => ['required', 'integer', 'exists:school_returns,id'], 'subject' => ['required', 'string', 'max:180'], 'message' => ['required', 'string', 'max:5000']]);
        $return = SchoolReturn::findOrFail($data['school_return_id']);
        Gate::authorize('view', $return);
        Gate::authorize('review', $return);
        abort_unless($return->submitted_at, 422, 'Select a submitted form.');
        $recipients = User::where('school_id', $return->school_id)->where('status', 'active')->with('roles.permissions')->get()->filter(fn ($u) => $u->isSchoolRepresentative());
        if ($recipients->isEmpty()) {
            return back()->withErrors(['school_return_id' => 'This school has no active representative account.'])->withInput();
        }
        DB::transaction(function () use ($recipients, $return, $data, $request) {
            foreach ($recipients as $recipient) {
                NotificationRecord::create(['user_id' => $recipient->id, 'sender_id' => $request->user()->id, 'school_id' => $return->school_id, 'school_return_id' => $return->id, 'subject' => $data['subject'], 'message' => $data['message'], 'type' => 'school_feedback', 'severity' => 'info', 'status' => 'open']);
            }
        });

        return redirect()->route('alerts.index')->with('status', 'Feedback sent to the school representative.');
    }

    public function read(Request $request, NotificationRecord $alert)
    {
        abort_unless($alert->user_id === $request->user()->id, 403);
        if ($request->user()->isSchoolRepresentative() && $alert->school_id) {
            abort_unless($alert->school_id === $request->user()->school_id, 403);
        }
        if (! $alert->read_at) {
            $alert->update(['read_at' => now(), 'status' => 'read']);
        }

        return back()->with('status','Alert marked as read.');
    }
}
