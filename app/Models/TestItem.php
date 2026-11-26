<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class TestItem extends Model
{
    protected $guarded = [];

    protected $casts = ['is_active' => 'boolean'];

    public function packages(): BelongsToMany
    {
        return $this->belongsToMany(TestPackage::class, 'package_test_item')
            ->withPivot('sort_order')
            ->withTimestamps()
            ->orderByPivot('sort_order');
    }
}
