<?php

namespace App\Services;

use App\Models\ReturnSection;
use App\Models\SchoolReturn;
use DomainException;

class EnrolmentSectionService
{
    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    public function save(SchoolReturn $return, array $rows): void
    {
        $this->assertEditable($return);
        $return->enrolmentStatistics()->delete();

        foreach ($rows as $row) {
            $boys = (int) ($row['boys'] ?? 0);
            $girls = (int) ($row['girls'] ?? 0);
            $total = $boys + $girls;
            $day = (int) ($row['day_scholars'] ?? 0);
            $boarders = (int) ($row['boarders'] ?? 0);

            if ($day + $boarders > $total) {
                throw new DomainException('day scholars plus boarders cannot exceed total enrolment.');
            }

            foreach (['repeaters', 'transfers_in', 'transfers_out', 'dropouts', 're_entries', 'completions'] as $field) {
                if ((int) ($row[$field] ?? 0) > $total) {
                    throw new DomainException("{$field} cannot exceed total enrolment.");
                }
            }

            $return->enrolmentStatistics()->create([
                ...$row,
                'boys' => $boys,
                'girls' => $girls,
                'total' => $total,
                'day_scholars' => $day,
                'boarders' => $boarders,
            ]);
        }

        $this->markComplete($return, 'enrolment');
    }

    private function assertEditable(SchoolReturn $return): void
    {
        if ($return->isLocked()) {
            throw new DomainException('locked returns cannot be edited.');
        }
    }

    private function markComplete(SchoolReturn $return, string $section): void
    {
        $return->sections()->updateOrCreate(['section' => $section], [
            'status' => ReturnSection::STATUS_COMPLETE,
            'validation_errors' => null,
            'completed_at' => now(),
        ]);
    }
}
