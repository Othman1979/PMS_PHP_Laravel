<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

/** Admin-visible audit trail: who did what, to which record, from where. */
#[Fillable(['user_id', 'action', 'subject_type', 'subject_id', 'description', 'ip', 'created_at'])]
class ActivityLog extends Model
{
    public const UPDATED_AT = null;

    public const ACTIONS = [
        'login', 'login_failed', 'logout',
        'request_created', 'request_transition',
        'equipment_created', 'equipment_updated', 'equipment_deleted',
        'user_created', 'user_updated',
        'purchase_decided', 'stock_adjusted',
        'settings_updated', 'backup_downloaded',
    ];

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function record(string $action, ?Model $subject = null, ?string $description = null, ?User $user = null): void
    {
        $user ??= Auth::user();

        static::query()->create([
            'user_id' => $user?->id,
            'action' => $action,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'description' => $description === null ? null : Str::limit($description, 480),
            'ip' => app()->runningInConsole() ? null : request()->ip(),
            'created_at' => now(),
        ]);
    }

    public function actionLabel(): string
    {
        return __('Activity_'.$this->action);
    }

    public function subjectUrl(): ?string
    {
        return match ($this->subject_type) {
            MaintenanceRequest::class => route('requests.show', $this->subject_id),
            Equipment::class => route('equipment.show', $this->subject_id),
            User::class => route('users.edit', $this->subject_id),
            PurchaseRequest::class => route('purchases.show', $this->subject_id),
            default => null,
        };
    }
}
