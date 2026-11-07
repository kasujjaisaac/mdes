<?php

namespace App\Services;

use App\Models\NotificationRecord;
use App\Models\ReportingPeriod;
use App\Models\User;

class SubmissionWindowNotificationService
{
    public function syncFor(User $user): void
    {
        if (! $user->isActive() || ! $user->isSchoolRepresentative() || ! $user->school_id) {
            return;
        }

        $today = now('Africa/Kampala')->toDateString();
        $periods = ReportingPeriod::where('status', ReportingPeriod::STATUS_OPEN)->where('is_archived', false)
            ->where(fn ($query) => $query->whereNull('opening_date')->orWhereDate('opening_date', '<=', $today))
            ->whereRaw('(COALESCE(extension_date, closing_date) IS NULL OR COALESCE(extension_date, closing_date) >= ?)', [$today])
            ->whereHas('schools', fn ($query) => $query->where('schools.id', $user->school_id))->get();

        foreach ($periods as $period) {
            $deadline = $period->extension_date ?? $period->closing_date;
            NotificationRecord::firstOrCreate([
                'notification_key' => 'window:'.$user->id.':'.$user->school_id.':'.$period->id.':'.($deadline?->toDateString() ?? 'open'),
            ], [
                'user_id' => $user->id,
                'school_id' => $user->school_id,
                'subject' => $period->title.' — submissions open',
                'type' => 'submission_window',
                'severity' => 'info',
                'status' => 'open',
                'message' => 'The '.$period->year.' submission window is open for your school.'
                    .($deadline ? ' Submit by '.$deadline->format('j F Y').' at 11:59 PM (Uganda time).' : '')
                    .' Open School forms on your dashboard to complete and submit your return.'
                    .($period->instructions ? "\n\n".$period->instructions : ''),
            ]);
        }
    }
}
