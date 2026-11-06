<?php

namespace App\Services;

use App\Models\Inspection;
use App\Models\InspectionFinding;
use DomainException;

class InspectionService
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function schedule(array $data): Inspection
    {
        return Inspection::query()->create([
            ...$data,
            'status' => $data['status'] ?? 'planned',
        ]);
    }

    public function closeFinding(InspectionFinding $finding, bool $reviewerApproved): void
    {
        if ($finding->severity === 'high' && ! $reviewerApproved) {
            throw new DomainException('high-risk findings require reviewer verification before closure.');
        }

        $finding->update(['status' => 'closed']);
    }
}
