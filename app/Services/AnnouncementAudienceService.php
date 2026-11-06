<?php

namespace App\Services;

use App\Models\School;
use App\Models\User;

class AnnouncementAudienceService
{
    public const LEVELS = ['nursery' => 'Pre-primary', 'primary' => 'Primary', 'secondary' => 'Secondary', 'vocational_technical' => 'Vocational', 'tertiary' => 'Tertiary'];

    public function resolve(User $sender, array $filters): array
    {
        $query = School::visibleTo($sender)->with(['parish', 'district'])->orderBy('official_name');
        $selectedSchools = $filters['school_ids'] ?? (! empty($filters['school_id']) ? [$filters['school_id']] : []);
        if (! empty($selectedSchools)) {
            $query->whereKey($selectedSchools);
        }
        foreach (['parish_ids' => 'parish_id', 'district_ids' => 'district_id', 'levels' => 'school_level', 'funding' => 'funding_classification', 'residence' => 'boarding_status', 'gender' => 'gender_classification'] as $key => $column) {
            if (empty($selectedSchools) && ! empty($filters[$key])) {
                $query->whereIn($column, $filters[$key]);
            }
        }
        $schools = $query->get();
        $recipients = User::whereIn('school_id', $schools->pluck('id'))->where('status', User::STATUS_ACTIVE)->with('roles.permissions')->get()->filter(fn ($user) => $user->isSchoolRepresentative())->values();
        $recipientSchoolIds = $recipients->pluck('school_id')->unique();

        return ['schools' => $schools, 'recipients' => $recipients, 'unreachable' => $schools->whereNotIn('id', $recipientSchoolIds)->values()];
    }
}
