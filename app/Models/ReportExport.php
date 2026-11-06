<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['generated_by', 'report_type', 'format', 'filters', 'status', 'generated_at', 'disk', 'path', 'failure_reason'])]
class ReportExport extends Model
{
    protected function casts(): array
    {
        return ['filters' => 'array', 'generated_at' => 'datetime'];
    }
}
