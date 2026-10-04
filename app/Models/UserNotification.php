<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** In-app copy of every notification a user receives (bell icon), stored in the user's language. */
#[Fillable(['user_id', 'title', 'body', 'url', 'tag', 'read_at'])]
class UserNotification extends Model
{
    protected function casts(): array
    {
        return ['read_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @param  Builder<UserNotification>  $query */
    public function scopeUnread(Builder $query): void
    {
        $query->whereNull('read_at');
    }

    /** @return array<string, mixed> */
    public function toLive(): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'body' => $this->body,
            'url' => $this->url,
            'tag' => $this->tag,
            'read' => $this->read_at !== null,
            'at' => $this->created_at->toIso8601String(),
            'ago' => $this->created_at->diffForHumans(),
        ];
    }
}
