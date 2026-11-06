<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['school_return_id', 'reviewer_id', 'outcome', 'comments'])]
class ReturnReview extends Model
{
}
