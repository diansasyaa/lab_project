<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Specimen extends Model
{
    protected $guarded = [];

    public function labRequest(): BelongsTo
    {
        return $this->belongsTo(LabRequest::class);
    }

    public function photos(): HasMany
    {
        return $this->hasMany(SpecimenPhoto::class, 'lab_request_id', 'lab_request_id');
    }
}
