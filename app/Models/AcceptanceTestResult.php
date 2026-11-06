<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['phase', 'scenario', 'expected_result', 'actual_result', 'status', 'severity'])]
class AcceptanceTestResult extends Model {}
