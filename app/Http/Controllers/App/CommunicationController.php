<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Models\HelpdeskTicket;
use App\Models\NotificationRecord;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class CommunicationController extends Controller
{
    public function announcements(): View
    {
        Gate::authorize('communications.view');

        return view('operations.index', [
            'title' => 'communications',
            'records' => Announcement::query()->latest()->paginate(20),
            'headers' => ['title', 'target', 'published', 'acknowledgement'],
            'rows' => fn (Announcement $item): array => [$item->title, $item->target_scope, $item->published_at?->toFormattedDateString() ?? 'draft', $item->requires_acknowledgement ? 'required' : 'not required'],
            'createRoute' => route('communications.create'),
        ]);
    }

    public function createAnnouncement(): View
    {
        Gate::authorize('communications.manage');

        return view('operations.communication-create', ['title' => 'publish announcement']);
    }

    public function storeAnnouncement(Request $request): RedirectResponse
    {
        Gate::authorize('communications.manage');
        Announcement::query()->create([
            ...$request->validate([
                'title' => ['required', 'string', 'max:180'],
                'body' => ['required', 'string', 'max:3000'],
                'target_scope' => ['required', 'string', 'max:80'],
                'requires_acknowledgement' => ['nullable', 'boolean'],
            ]),
            'created_by' => $request->user()->id,
            'published_at' => now(),
        ]);

        return redirect()->route('communications.index')->with('status', 'announcement published.');
    }

    public function helpdesk(): View
    {
        Gate::authorize('helpdesk.view');

        return view('operations.index', [
            'title' => 'helpdesk',
            'records' => HelpdeskTicket::query()->when(request()->user()->isSchoolRepresentative() || ! request()->user()->hasPermission('returns.review'), fn ($q) => $q->where('opened_by', request()->user()->id))->latest()->paginate(20),
            'headers' => ['category', 'priority', 'status', 'description'],
            'rows' => fn (HelpdeskTicket $ticket): array => [$ticket->category, $ticket->priority, $ticket->status, str($ticket->description)->limit(70)],
            'createRoute' => route('helpdesk.create'),
        ]);
    }

    public function notifications(): View
    {
        Gate::authorize('communications.view');

        return view('operations.index', [
            'title' => 'notifications',
            'records' => NotificationRecord::query()->where('user_id', request()->user()->id)->latest()->paginate(20),
            'headers' => ['type', 'severity', 'status', 'message'],
            'rows' => fn (NotificationRecord $notification): array => [$notification->type, $notification->severity, $notification->status, str($notification->message)->limit(80)],
            'createRoute' => null,
        ]);
    }

    public function createTicket(): View
    {
        Gate::authorize('helpdesk.view');

        return view('operations.helpdesk-create', ['title' => 'open helpdesk ticket']);
    }

    public function storeTicket(Request $request): RedirectResponse
    {
        Gate::authorize('helpdesk.view');
        HelpdeskTicket::query()->create([
            ...$request->validate([
                'category' => ['required', 'string', 'max:80'],
                'priority' => ['required', 'in:low,normal,high,urgent'],
                'description' => ['required', 'string', 'max:3000'],
            ]),
            'opened_by' => $request->user()->id,
        ]);

        return redirect()->route('helpdesk.index')->with('status', 'ticket opened.');
    }
}
