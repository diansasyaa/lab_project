<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CsValidation extends Model
{
    protected $guarded = [];

    protected $casts = [
        'validated_at' => 'datetime',
        'estimated_completion_date' => 'date',
    ];

    public function labRequest(): BelongsTo
    {
        return $this->belongsTo(LabRequest::class);
    }

    public function csUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cs_user_id');
    }
}
