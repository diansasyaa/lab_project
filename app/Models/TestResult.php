<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TestResult extends Model
{
    protected $guarded = [];

    protected $casts = ['submitted_at' => 'datetime'];

    public function requestTestItem(): BelongsTo
    {
        return $this->belongsTo(LabRequestTestItem::class, 'lab_request_test_item_id');
    }

    public function details(): HasMany
    {
        return $this->hasMany(TestItemDetail::class, 'test_result_id');
    }
}
