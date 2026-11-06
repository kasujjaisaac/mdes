<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\NotificationRecord;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class CommunicationController extends Controller
{
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
}
