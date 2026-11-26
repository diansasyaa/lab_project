<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

class LabRequestTestItem extends Model
{
    protected $guarded = [];

    protected $casts = ['selected' => 'boolean'];

    public function result(): HasOne
    {
        return $this->hasOne(TestResult::class, 'lab_request_test_item_id');
    }
}
