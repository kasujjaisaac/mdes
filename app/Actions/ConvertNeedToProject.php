<?php

namespace App\Actions;

use App\Models\DevelopmentNeed;
use App\Models\DevelopmentProject;
use DomainException;

class ConvertNeedToProject
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(DevelopmentNeed $need, array $data): DevelopmentProject
    {
        if (! in_array($need->review_status, ['approved', 'verified'], true)) {
            throw new DomainException('only verified or approved needs can become projects.');
        }

        if ($need->project()->exists()) {
            throw new DomainException('this need already has a project.');
        }

        $project = DevelopmentProject::query()->create([
            'development_need_id' => $need->id,
            'school_id' => $need->school_id,
            'title' => $data['title'] ?? $need->category.' project',
            'approved_budget' => $data['approved_budget'] ?? 0,
            'funder' => $data['funder'] ?? null,
            'implementer' => $data['implementer'] ?? null,
            'start_date' => $data['start_date'] ?? null,
            'planned_completion_date' => $data['planned_completion_date'] ?? null,
            'status' => 'planned',
        ]);

        $need->update(['funding_status' => 'funded']);

        return $project;
    }
}
