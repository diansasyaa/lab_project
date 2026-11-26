<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class TestPackage extends Model
{
    protected $guarded = [];

    protected $casts = ['is_active' => 'boolean'];

    public function testItems(): BelongsToMany
    {
        return $this->belongsToMany(TestItem::class, 'package_test_item')
            ->withPivot('sort_order')
            ->withTimestamps()
            ->orderByPivot('sort_order');
    }
}
