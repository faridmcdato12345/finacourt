<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['quota_date', 'reserved_count'])]
class OutreachDailyQuota extends Model
{
    protected function casts(): array
    {
        return ['quota_date' => 'immutable_date'];
    }
}
