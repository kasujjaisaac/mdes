<?php

namespace App\Services;

use App\Models\FinancialStatistic;
use App\Models\School;

class FinancialStatisticsService
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function save(School $school, array $data): FinancialStatistic
    {
        $budget = $this->sumCategories($data['budget_categories'] ?? []);
        $income = $this->sumCategories($data['income_categories'] ?? []);
        $expenditure = $this->sumCategories($data['expenditure_categories'] ?? []);

        return FinancialStatistic::query()->updateOrCreate([
            'school_id' => $school->id,
            'budget_year' => $data['budget_year'],
        ], [
            ...$data,
            'approved_budget_total' => $budget,
            'income_total' => $income,
            'expenditure_total' => $expenditure,
            'variance' => $budget - $expenditure,
        ]);
    }

    /**
     * @param  array<string, mixed>  $categories
     */
    private function sumCategories(array $categories): float
    {
        return (float) collect($categories)->sum(fn (mixed $amount): float => (float) $amount);
    }
}
