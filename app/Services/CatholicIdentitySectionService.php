<?php

namespace App\Services;

use App\Models\ReturnSection;
use App\Models\SchoolReturn;
use DomainException;

class CatholicIdentitySectionService
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function save(SchoolReturn $return, array $data): void
    {
        if ($return->isLocked()) {
            throw new DomainException('locked returns cannot be edited.');
        }

        $return->catholicIdentityStatistic()->updateOrCreate(['school_return_id' => $return->id], [
            ...$data,
            'has_chaplaincy' => (bool) ($data['has_chaplaincy'] ?? false),
            'catechesis_available' => (bool) ($data['catechesis_available'] ?? false),
            'values_education_available' => (bool) ($data['values_education_available'] ?? false),
            'safeguarding_structure_available' => (bool) ($data['safeguarding_structure_available'] ?? false),
            'counselling_structure_available' => (bool) ($data['counselling_structure_available'] ?? false),
        ]);

        $return->sections()->updateOrCreate(['section' => 'catholic_identity'], [
            'status' => ReturnSection::STATUS_COMPLETE,
            'validation_errors' => null,
            'completed_at' => now(),
        ]);
    }
}
