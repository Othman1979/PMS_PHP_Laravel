<?php

namespace App\Models;

use App\Enums\DepartmentConfirmation;
use App\Enums\RequestStatus;
use App\Enums\Role;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'request_number', 'equipment_id', 'department_id', 'created_by_id', 'description', 'priority_id', 'fault_type_id', 'fault_cause_id', 'status',
    'assigned_technician_id', 'assigned_at', 'due_at', 'escalated_at', 'accepted_at', 'started_at', 'completed_at', 'closed_at',
    'is_under_warranty', 'is_preventive', 'preventive_maintenance_plan_id', 'cost_labor', 'cost_parts',
    'resolution_notes', 'technician_notes', 'department_confirmation',
])]
class MaintenanceRequest extends Model
{
    protected function casts(): array
    {
        return [
            'status' => RequestStatus::class,
            'department_confirmation' => DepartmentConfirmation::class,
            'assigned_at' => 'datetime',
            'due_at' => 'datetime',
            'escalated_at' => 'datetime',
            'accepted_at' => 'datetime',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'closed_at' => 'datetime',
            'is_under_warranty' => 'boolean',
            'is_preventive' => 'boolean',
            'cost_labor' => 'decimal:2',
            'cost_parts' => 'decimal:2',
        ];
    }

    public function equipment(): BelongsTo
    {
        return $this->belongsTo(Equipment::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function priority(): BelongsTo
    {
        return $this->belongsTo(Priority::class);
    }

    public function faultType(): BelongsTo
    {
        return $this->belongsTo(FaultType::class);
    }

    public function faultCause(): BelongsTo
    {
        return $this->belongsTo(FaultCause::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    public function assignedTechnician(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_technician_id');
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(PreventiveMaintenancePlan::class, 'preventive_maintenance_plan_id');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(RequestAttachment::class, 'request_id');
    }

    public function timeline(): HasMany
    {
        return $this->hasMany(RequestTimeline::class, 'request_id');
    }

    public function partsUsed(): HasMany
    {
        return $this->hasMany(RequestPartUsed::class, 'request_id');
    }

    public function checklistResults(): HasMany
    {
        return $this->hasMany(ChecklistResult::class, 'request_id');
    }

    public function totalCost(): float
    {
        return (float) $this->cost_labor + (float) $this->cost_parts;
    }

    public function isOpen(): bool
    {
        return ! in_array($this->status, RequestStatus::closed(), true);
    }

    /** Past the SLA deadline of its priority and still not completed. */
    public function isOverdue(): bool
    {
        return $this->due_at !== null && $this->isOpen() && $this->due_at->isPast();
    }

    #[Scope]
    protected function overdue(Builder $query): void
    {
        $query->whereNotNull('due_at')->where('due_at', '<', now())->whereNotIn('status', RequestStatus::closedValues());
    }

    /** Requests a user may see: staff see all, technicians their own + untriaged, managers their department, others their own. */
    #[Scope]
    protected function visibleTo(Builder $query, User $user): void
    {
        match ($user->role) {
            Role::Admin, Role::Coordinator => null,
            Role::DepartmentManager => $query->where('department_id', $user->department_id),
            Role::Technician => $query->where(fn (Builder $q) => $q
                ->where('assigned_technician_id', $user->id)
                ->orWhereIn('status', [RequestStatus::New, RequestStatus::UnderReview])),
            default => $query->where('created_by_id', $user->id),
        };
    }

    public function isVisibleTo(User $user): bool
    {
        return match ($user->role) {
            Role::Admin, Role::Coordinator => true,
            Role::DepartmentManager => $this->department_id === $user->department_id,
            Role::Technician => $this->assigned_technician_id === $user->id
                || in_array($this->status, [RequestStatus::New, RequestStatus::UnderReview], true),
            default => $this->created_by_id === $user->id,
        };
    }

    /** Requester or the requester's department manager (and admin) may confirm / reopen / cancel. */
    public function isRequesterSide(User $user): bool
    {
        return $this->created_by_id === $user->id
            || ($user->role === Role::DepartmentManager && $this->department_id === $user->department_id)
            || $user->isAdmin();
    }

    public function isAssignedTo(User $user): bool
    {
        return $user->isAdmin() || $this->assigned_technician_id === $user->id;
    }
}
