<?php

namespace App\Services;

use App\Models\School;

class SchoolProfileContactService
{
    public function sync(School $school, array $data): void
    {
        if (array_key_exists('contact', $data)) {
            $school->contact()->updateOrCreate(['school_id' => $school->id], $data['contact']);
        }
        if (! array_key_exists('headteacher_name', $data) || blank($data['headteacher_name'])) {
            return;
        }
        $head = $school->currentHeadteacher()->first();
        $values = ['person_name' => $data['headteacher_name']];
        if (array_key_exists('headteacher_phone', $data)) {
            $values['telephone'] = $data['headteacher_phone'];
        }
        if (array_key_exists('headteacher_email', $data)) {
            $values['email'] = $data['headteacher_email'];
        }
        if ($head && $head->person_name === $data['headteacher_name']) {
            $head->update($values);
        } else {
            $school->leadershipHistories()->where('position', 'headteacher')->where('is_current', true)->update(['is_current' => false, 'effective_to' => now()->toDateString()]);
            $school->leadershipHistories()->create([...$values, 'position' => 'headteacher', 'effective_from' => now()->toDateString(), 'is_current' => true]);
        }
    }
}
