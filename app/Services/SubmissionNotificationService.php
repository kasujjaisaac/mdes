<?php

namespace App\Services;

use App\Models\NotificationRecord;
use App\Models\Role;
use App\Models\SchoolReturn;
use App\Models\SubmissionVersion;
use App\Models\User;

class SubmissionNotificationService
{
    public function send(SchoolReturn $return, User $actor, string $action, ?string $reason = null, bool $resubmission = false): int
    {
        $version = SubmissionVersion::where('school_return_id', $return->id)->latest('version')->first();
        $key = 'submission:'.$return->id.':'.($version?->id ?? 'legacy').':'.$action;
        $reference = 'MDES-'.$return->id.($version ? ' version '.$version->version : '');
        $school = User::where('school_id', $return->school_id)->whereHas('roles', fn ($q) => $q->where('slug', Role::HEADTEACHER))->with('roles')->get()->first(fn ($u) => $u->hasValidPortalRole());
        $delivered = 0;
        if ($action === 'submit') {
            foreach (User::administrators()->where('status', User::STATUS_ACTIVE)->get() as $admin) {
                $this->notice($admin, $actor, $return, $key.':admin:'.$admin->id, 'submission_received', $resubmission ? 'Resubmission received' : 'New school submission',
                    $return->school->official_name.' has '.($resubmission ? 'resubmitted' : 'submitted').' its '.$return->reportingPeriod->year.' form. Reference: '.$reference.'. Open the submission to review it.');
                $delivered++;
            }
            if ($school) {
                $this->notice($school, $actor, $return, $key.':school:'.$school->id, 'submission_receipt', 'Submission received',
                    'Your form has been received and locked for admin review. Reference: '.$reference.'. Keep a copy using Download form.');
            }
        } elseif ($school) {
            $this->notice($school, $actor, $return, $key.':school:'.$school->id, 'school_feedback', $action === 'approve' ? 'Submission approved' : 'Submission returned for correction',
                $action === 'approve' ? 'Your school submission has been approved. Reference: '.$reference.'.' : ($reason ?? ''));
            $delivered++;
        }

        return $delivered;
    }

    private function notice(User $recipient, User $actor, SchoolReturn $return, string $key, string $type, string $subject, string $message): void
    {
        NotificationRecord::firstOrCreate(['notification_key' => $key], ['user_id' => $recipient->id, 'sender_id' => $actor->id, 'school_id' => $return->school_id, 'school_return_id' => $return->id, 'type' => $type, 'severity' => 'info', 'subject' => $subject, 'message' => $message, 'status' => 'open']);
    }
}
