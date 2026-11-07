<?php

namespace Tests\Feature;

use App\Models\NotificationRecord;
use App\Models\ReportingPeriod;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\LocalSchoolAccountSeeder;
use Database\Seeders\LocalSubmissionWindowSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubmissionWindowNoticeTest extends TestCase
{
    use RefreshDatabase;

    public function test_school_can_start_an_available_form_from_my_submissions(): void
    {
        $this->seed([DatabaseSeeder::class, LocalSchoolAccountSeeder::class, LocalSubmissionWindowSeeder::class]);
        $user = User::where('username', 'DEMO-MOE-2026')->firstOrFail();
        $period = ReportingPeriod::where('annual_year', now('Africa/Kampala')->year)->firstOrFail();
        $startUrl = route('returns.start', [$period, $user->school_id]);
        $this->actingAs($user)->get(route('returns.index'))->assertOk()->assertSee('Start form')
            ->assertSee($startUrl)->assertViewHas('startablePeriods', fn ($periods) => $periods->count() === 1);
        $this->post($startUrl)->assertRedirect();
        $this->assertDatabaseHas('school_returns', ['school_id' => $user->school_id, 'reporting_period_id' => $period->id, 'status' => 'draft']);
        $this->get(route('returns.index'))->assertOk()->assertDontSee('action="'.$startUrl.'"', false)
            ->assertViewHas('startablePeriods', fn ($periods) => $periods->isEmpty());
    }

    public function test_open_window_notice_populates_inbox_and_bell_once_and_can_be_read(): void
    {
        $this->seed([DatabaseSeeder::class, LocalSchoolAccountSeeder::class, LocalSubmissionWindowSeeder::class]);
        $user = User::where('username', 'DEMO-MOE-2026')->firstOrFail();
        $this->actingAs($user)->get(route('representative.home'))->assertOk()
            ->assertSee('Inbox: 1 unread')->assertSee('href="'.route('announcements.index').'">Read notice', false);
        $this->get(route('announcements.index'))->assertOk()->assertSee('submissions open')->assertSee('Open school forms');
        $this->assertDatabaseCount('notification_records', 1);
        $notice = NotificationRecord::firstOrFail();
        $this->post(route('alerts.read', $notice))->assertRedirect();
        $this->get(route('announcements.index'))->assertOk()->assertSee('Inbox: 0 unread');
        $this->assertDatabaseCount('notification_records', 1);
        $this->assertNotNull($notice->fresh()->read_at);
    }

    public function test_closed_and_unassigned_windows_do_not_generate_notices(): void
    {
        $this->seed([DatabaseSeeder::class, LocalSchoolAccountSeeder::class, LocalSubmissionWindowSeeder::class]);
        $user = User::where('username', 'DEMO-MOE-2026')->firstOrFail();
        $period = ReportingPeriod::where('annual_year', now('Africa/Kampala')->year)->firstOrFail();
        $period->update(['status' => ReportingPeriod::STATUS_CLOSED]);
        $this->actingAs($user)->get(route('announcements.index'))->assertOk();
        $this->assertDatabaseCount('notification_records', 0);
        $period->update(['status' => ReportingPeriod::STATUS_OPEN]);
        $period->schools()->detach($user->school_id);
        $this->get(route('announcements.index'))->assertOk();
        $this->assertDatabaseCount('notification_records', 0);
    }
}
