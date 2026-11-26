<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class LabRequest extends Model
{
    protected $guarded = [];

    protected $casts = [
        'test_start_date' => 'date',
        'issue_date' => 'date',
        'previous_test' => 'boolean',
        'retest' => 'boolean',
    ];

    public function requestor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requestor_id');
    }

    public function testPackage(): BelongsTo
    {
        return $this->belongsTo(TestPackage::class);
    }

    public function testItems(): BelongsToMany
    {
        return $this->belongsToMany(TestItem::class, 'lab_request_test_items')
            ->withPivot(['id', 'selected'])
            ->withTimestamps();
    }

    public function specimen(): HasOne
    {
        return $this->hasOne(Specimen::class);
    }

    public function specimenPhotos(): HasMany
    {
        return $this->hasMany(SpecimenPhoto::class);
    }

    public function csValidation(): HasOne
    {
        return $this->hasOne(CsValidation::class);
    }

    public function finalReport(): HasOne
    {
        return $this->hasOne(FinalReport::class);
    }
}
