<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'confirmed_at', 'worked_at', 'worked_as_lead', 'removed_at'])]
class RequestTechnician extends Model
{
    protected function casts(): array
    {
        return [
            'confirmed_at' => 'datetime',
            'worked_at' => 'datetime',
            'worked_as_lead' => 'boolean',
            'removed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function request(): BelongsTo
    {
        return $this->belongsTo(MaintenanceRequest::class, 'request_id');
    }
}
