<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\HelpdeskTicket;
use App\Models\NotificationRecord;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SupportController extends Controller
{
    private function manages($user): bool
    {
        return ! $user->isSchoolRepresentative() && $user->hasPermission('returns.review');
    }

    public function index(Request $request)
    {
        $manager = $this->manages($request->user());
        $tickets = HelpdeskTicket::query()->when(! $manager, fn ($q) => $q->where('opened_by', $request->user()->id))->with('messages')->latest()->paginate(10);

        return view('support.index', ['title' => 'Support', 'tickets' => $tickets, 'manager' => $manager]);
    }

    public function store(Request $request)
    {
        $data = $request->validate(['category' => ['required', 'in:School profile,Form entry,Submission,Account access,Other'], 'description' => ['required', 'string', 'min:10', 'max:3000']]);
        DB::transaction(function () use ($request, $data) {
            $ticket = HelpdeskTicket::create([...$data, 'school_id' => $request->user()->school_id, 'opened_by' => $request->user()->id, 'priority' => 'normal', 'status' => 'open']);
            User::where('status', 'active')->with('roles.permissions')->get()->filter(fn ($u) => $this->manages($u))->each(fn ($admin) => NotificationRecord::create(['user_id' => $admin->id, 'sender_id' => $request->user()->id, 'school_id' => $request->user()->school_id, 'type' => 'support_request', 'subject' => 'Support request #'.$ticket->id, 'message' => $data['description'], 'severity' => 'info', 'status' => 'open']));
        });

        return redirect()->route('support.index')->with('status', 'Your support request has been sent.');
    }

    public function reply(Request $request, HelpdeskTicket $ticket)
    {
        abort_unless($this->manages($request->user()), 403);
        $data = $request->validate(['message' => ['required', 'string', 'max:3000'], 'status' => ['required', 'in:open,resolved']]);
        DB::transaction(function () use ($request, $ticket, $data) {
            $ticket->messages()->create(['author_id' => $request->user()->id, 'message' => $data['message']]);
            $ticket->update(['status' => $data['status'], 'assigned_to' => $request->user()->id]);
            if ($ticket->opened_by) {
                NotificationRecord::create(['user_id' => $ticket->opened_by, 'sender_id' => $request->user()->id, 'school_id' => $ticket->school_id, 'type' => 'support_reply', 'subject' => 'Support reply #'.$ticket->id, 'message' => $data['message'], 'severity' => 'info', 'status' => 'open']);
            }
        });

        return back()->with('status', 'Support reply sent.');
    }
}
