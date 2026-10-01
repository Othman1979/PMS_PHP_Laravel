<?php

namespace App\Models;

use App\Enums\RequestStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['request_id', 'status_from', 'status_to', 'changed_by_id', 'changed_at', 'note'])]
class RequestTimeline extends Model
{
    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'status_from' => RequestStatus::class,
            'status_to' => RequestStatus::class,
            'changed_at' => 'datetime',
        ];
    }

    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by_id');
    }

    public function isNote(): bool
    {
        return $this->status_from !== null && $this->status_from === $this->status_to;
    }
}
