<?php

namespace App\Http\Controllers\App;

use App\Actions\RecordAuditEvent;
use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Models\NotificationRecord;
use App\Models\School;
use App\Services\AnnouncementAudienceService;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class AnnouncementController extends Controller
{
    private function authorizePublisher(Request $request): void
    {
        abort_unless($request->user()->isAdministrator(), 403);
    }

    public function index(Request $request)
    {
        if (! $request->user()->isAdministrator()) {
            return app(AlertController::class)->index($request);
        }

        $announcements = Announcement::with(['deliveries.school', 'creator'])->withCount('deliveries')->latest()->paginate(15);
        $schoolIds = $announcements->getCollection()->flatMap(fn ($announcement) => $announcement->target_filters['school_ids'] ?? [])->unique();
        $audienceSchools = School::whereIn('id', $schoolIds)->get()->keyBy('id');

        return view('announcements.index', ['title' => 'Announcements', 'announcements' => $announcements, 'audienceSchools' => $audienceSchools]);
    }

    public function create(Request $request)
    {
        $this->authorizePublisher($request);
        $schools = School::visibleTo($request->user())->with(['parish', 'district'])->orderBy('official_name')->get();

        return view('announcements.create', ['title' => 'New announcement', 'schools' => $schools, 'parishes' => $schools->pluck('parish')->filter()->unique('id')->sortBy('name'), 'districts' => $schools->pluck('district')->filter()->unique('id')->sortBy('name')]);
    }

    public function preview(Request $request, AnnouncementAudienceService $audiences)
    {
        $this->authorizePublisher($request);
        $rules = ['title' => ['required', 'string', 'max:180'], 'body' => ['required', 'string', 'max:5000'], 'school_ids' => ['nullable', 'array', 'max:2000'], 'school_ids.*' => ['required', 'integer', 'distinct', Rule::in(School::visibleTo($request->user())->pluck('id')->all())]];
        foreach (['parish_ids', 'district_ids', 'levels', 'funding', 'residence', 'gender'] as $key) {
            $rules[$key] = ['nullable', 'array', 'max:2000'];
        }
        $rules += ['parish_ids.*' => ['integer', 'distinct', 'exists:parishes,id'], 'district_ids.*' => ['integer', 'distinct', 'exists:districts,id'],
            'levels.*' => ['string', 'distinct', Rule::in(array_keys(AnnouncementAudienceService::LEVELS))],
            'funding.*' => ['string', 'distinct', Rule::in(['Government', 'Private'])],
            'residence.*' => ['string', 'distinct', Rule::in(['Day', 'Boarding', 'Both day and boarding'])],
            'gender.*' => ['string', 'distinct', Rule::in(['Boys', 'Girls', 'Mixed'])]];
        $data = $request->validate($rules);
        $audience = $audiences->resolve($request->user(), $data);
        $request->flashOnly(array_keys($data));
        $payload = ['data' => $data, 'sender_id' => $request->user()->id, 'school_ids' => $audience['schools']->pluck('id')->sort()->values()->all(), 'recipient_ids' => $audience['recipients']->pluck('id')->sort()->values()->all(), 'expires_at' => now()->addMinutes(30)->timestamp, 'key' => (string) Str::uuid()];
        $token = Crypt::encryptString(json_encode($payload, JSON_THROW_ON_ERROR));

        return view('announcements.preview', ['title' => 'Preview announcement', 'data' => $data, 'audience' => $audience, 'token' => $token]);
    }

    public function publish(Request $request, AnnouncementAudienceService $audiences, RecordAuditEvent $audit)
    {
        $this->authorizePublisher($request);
        $request->validate(['preview_token' => ['required', 'string', 'max:262144']]);
        try {
            $payload = json_decode(Crypt::decryptString((string) $request->input('preview_token')), true, 512, JSON_THROW_ON_ERROR);
        } catch (DecryptException|\JsonException $exception) {
            return redirect()->route('announcements.create')->withErrors(['preview_token' => 'Preview the announcement before publishing.']);
        }
        abort_unless(($payload['sender_id'] ?? null) === $request->user()->id, 403);
        if ($existing = Announcement::where('publication_key', $payload['key'])->first()) {
            return redirect()->route('announcements.index')->with('status', 'This announcement has already been published.');
        }
        $data = $payload['data'];
        if ($payload['expires_at'] < now()->timestamp) {
            return redirect()->route('announcements.create')->withInput($data)->withErrors(['preview_token' => 'The preview expired. Review the audience again.']);
        }
        $audience = $audiences->resolve($request->user(), $data);
        if ($audience['schools']->pluck('id')->sort()->values()->all() !== $payload['school_ids'] || $audience['recipients']->pluck('id')->sort()->values()->all() !== $payload['recipient_ids']) {
            return redirect()->route('announcements.create')->withInput($data)->withErrors(['preview_token' => 'The audience changed. Preview it again before publishing.']);
        }
        if ($audience['recipients']->isEmpty()) {
            return redirect()->route('announcements.create')->withInput($data)->withErrors(['audience' => 'No active school headteacher accounts match this audience.']);
        }
        try {
            DB::transaction(function () use ($request, $payload, $data, $audience, $audit) {
                $announcement = Announcement::create(['created_by' => $request->user()->id, 'title' => $data['title'], 'body' => $data['body'], 'target_scope' => 'registered_schools', 'target_filters' => ['criteria' => array_diff_key($data, array_flip(['title', 'body'])), 'school_ids' => $payload['school_ids'], 'unreachable_school_ids' => $audience['unreachable']->pluck('id')->all()], 'requires_acknowledgement' => true, 'published_at' => now(), 'publication_key' => $payload['key']]);
                foreach ($audience['recipients'] as $recipient) {
                    NotificationRecord::create(['announcement_id' => $announcement->id, 'user_id' => $recipient->id, 'sender_id' => $request->user()->id, 'school_id' => $recipient->school_id, 'subject' => $data['title'], 'message' => $data['body'], 'type' => 'announcement', 'severity' => 'info', 'status' => 'open']);
                }
                $audit->handle($request, 'announcement.published', $announcement, null, ['school_count' => $audience['schools']->count(), 'recipient_count' => $audience['recipients']->count()]);
            });
        } catch (UniqueConstraintViolationException $exception) {
            if (! Announcement::where('publication_key', $payload['key'])->exists()) {
                throw $exception;
            }
        }

        return redirect()->route('announcements.index')->with('status', 'Announcement published to '.$audience['recipients']->count().' school headteacher accounts.');
    }
}
