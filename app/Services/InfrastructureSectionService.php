<?php

namespace App\Services;

use App\Models\ReturnSection;
use App\Models\SchoolReturn;
use DomainException;

class InfrastructureSectionService
{
    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    public function save(SchoolReturn $return, array $rows): void
    {
        if ($return->isLocked()) {
            throw new DomainException('locked returns cannot be edited.');
        }

        $return->infrastructureStatistics()->delete();

        foreach ($rows as $row) {
            $total = (int) ($row['total'] ?? 0);
            $functional = (int) ($row['functional'] ?? 0);
            $damaged = (int) ($row['damaged'] ?? 0);
            $required = (int) ($row['required'] ?? 0);

            if ($functional > $total) {
                throw new DomainException('functional facilities cannot exceed total facilities.');
            }

            if ($damaged > $total) {
                throw new DomainException('damaged facilities cannot exceed total facilities.');
            }

            $return->infrastructureStatistics()->create([
                ...$row,
                'total' => $total,
                'functional' => $functional,
                'damaged' => $damaged,
                'required' => $required,
                'gap' => max(0, $required - $functional),
                'has_electricity' => (bool) ($row['has_electricity'] ?? false),
                'has_internet' => (bool) ($row['has_internet'] ?? false),
                'fire_safety_available' => (bool) ($row['fire_safety_available'] ?? false),
                'accessibility_available' => (bool) ($row['accessibility_available'] ?? false),
                'security_fenced' => (bool) ($row['security_fenced'] ?? false),
                'high_risk_condition' => (bool) ($row['high_risk_condition'] ?? false),
            ]);
        }

        $return->sections()->updateOrCreate(['section' => 'infrastructure'], [
            'status' => ReturnSection::STATUS_COMPLETE,
            'validation_errors' => null,
            'completed_at' => now(),
        ]);
    }
}
