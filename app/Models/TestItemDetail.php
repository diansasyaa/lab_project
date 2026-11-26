<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TestItemDetail extends Model
{
    protected $table = 'test_item_details';

    protected $primaryKey = 'id_detail';

    protected $guarded = [];

    protected $casts = ['measurements' => 'array'];

    public function testResult(): BelongsTo
    {
        return $this->belongsTo(TestResult::class, 'test_result_id');
    }
}
