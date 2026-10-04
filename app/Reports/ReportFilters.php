<?php

namespace App\Reports;

use App\Enums\RequestStatus;
use App\Enums\StockMovementType;
use App\Models\Department;
use App\Models\Equipment;
use App\Models\FaultCause;
use App\Models\FaultType;
use App\Models\MaintenanceRequest;
use App\Models\SparePart;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * Filter state shared by every report. Only the keys a report declares are read from
 * the request, so a filter left in the query string by another report has no effect.
 */
final class ReportFilters
{
    public const KEYS = ['from', 'to', 'department_id', 'equipment_id', 'technician_id', 'fault_type_id', 'fault_cause_id', 'state', 'spare_part_id', 'movement_type'];

    public function __construct(
        public readonly ?CarbonImmutable $from,
        public readonly ?CarbonImmutable $to,
        public readonly ?int $departmentId,
        public readonly ?int $equipmentId,
        public readonly ?int $technicianId,
        public readonly ?int $faultTypeId,
        public readonly ?int $faultCauseId,
        public readonly string $state,
        public readonly ?int $sparePartId,
        public readonly ?StockMovementType $movementType,
        /** @var list<string> */
        public readonly array $keys,
    ) {}

    /** @param list<string> $keys */
    public static function fromRequest(Request $request, array $keys, ?CarbonImmutable $defaultFrom): self
    {
        $has = fn (string $k) => in_array($k, $keys, true) && $request->filled($k);
        $int = fn (string $k) => $has($k) ? $request->integer($k) : null;
        $from = $has('from') ? self::date($request->query('from')) : null;
        $to = $has('to') ? self::date($request->query('to')) : null;
        $state = $request->query('state');

        return new self(
            from: in_array('from', $keys, true) ? ($from ?? $defaultFrom) : null,
            to: in_array('to', $keys, true) ? ($to ?? CarbonImmutable::today()) : null,
            departmentId: $int('department_id'),
            equipmentId: $int('equipment_id'),
            technicianId: $int('technician_id'),
            faultTypeId: $int('fault_type_id'),
            faultCauseId: $int('fault_cause_id'),
            state: $has('state') && in_array($state, ['open', 'closed'], true) ? $state : '',
            sparePartId: $int('spare_part_id'),
            movementType: $has('movement_type') ? StockMovementType::tryFrom((string) $request->query('movement_type')) : null,
            keys: $keys,
        );
    }

    public function has(string $key): bool
    {
        return in_array($key, $this->keys, true);
    }

    /** Query-string representation, used to carry the filters across tabs and exports. */
    public function toQuery(): array
    {
        return array_filter([
            'from' => $this->from?->format('Y-m-d'),
            'to' => $this->to?->format('Y-m-d'),
            'department_id' => $this->departmentId,
            'equipment_id' => $this->equipmentId,
            'technician_id' => $this->technicianId,
            'fault_type_id' => $this->faultTypeId,
            'fault_cause_id' => $this->faultCauseId,
            'state' => $this->state !== '' ? $this->state : null,
            'spare_part_id' => $this->sparePartId,
            'movement_type' => $this->movementType?->value,
        ], fn (mixed $v) => $v !== null && $v !== '');
    }

    /** @return Builder<MaintenanceRequest> */
    public function requests(): Builder
    {
        $closed = RequestStatus::closed();

        return MaintenanceRequest::query()
            ->when($this->from, fn (Builder $q) => $q->where('created_at', '>=', $this->from->startOfDay()))
            ->when($this->to, fn (Builder $q) => $q->where('created_at', '<=', $this->to->endOfDay()))
            ->when($this->departmentId, fn (Builder $q) => $q->where('department_id', $this->departmentId))
            ->when($this->equipmentId, fn (Builder $q) => $q->where('equipment_id', $this->equipmentId))
            ->when($this->technicianId, fn (Builder $q) => $q->where('assigned_technician_id', $this->technicianId))
            ->when($this->faultTypeId, fn (Builder $q) => $q->where('fault_type_id', $this->faultTypeId))
            ->when($this->faultCauseId, fn (Builder $q) => $q->where('fault_cause_id', $this->faultCauseId))
            ->when($this->state === 'open', fn (Builder $q) => $q->whereNotIn('status', $closed))
            ->when($this->state === 'closed', fn (Builder $q) => $q->whereIn('status', $closed));
    }

    /**
     * Human-readable "label: value" pairs of the active filters, for export headers.
     *
     * @return array<string, string>
     */
    public function describe(): array
    {
        $lines = [];
        if ($this->from) {
            $lines[__('From')] = $this->from->format('Y-m-d');
        }
        if ($this->to) {
            $lines[__('To')] = $this->to->format('Y-m-d');
        }
        if ($this->departmentId) {
            $lines[__('Department')] = Department::find($this->departmentId)?->localized_name ?? '-';
        }
        if ($this->equipmentId) {
            $lines[__('Equipment')] = Equipment::find($this->equipmentId)?->name ?? '-';
        }
        if ($this->technicianId) {
            $lines[__('Technician')] = User::find($this->technicianId)?->full_name ?? '-';
        }
        if ($this->faultTypeId) {
            $lines[__('FaultType')] = FaultType::find($this->faultTypeId)?->localized_name ?? '-';
        }
        if ($this->faultCauseId) {
            $lines[__('FaultCause')] = FaultCause::find($this->faultCauseId)?->localized_name ?? '-';
        }
        if ($this->state !== '') {
            $lines[__('RequestState')] = $this->state === 'open' ? __('Open') : __('ClosedRequests');
        }
        if ($this->sparePartId) {
            $lines[__('SparePart')] = SparePart::find($this->sparePartId)?->name ?? '-';
        }
        if ($this->movementType) {
            $lines[__('MovementType')] = $this->movementType->label();
        }

        return $lines;
    }

    private static function date(mixed $value): ?CarbonImmutable
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        try {
            return CarbonImmutable::createFromFormat('Y-m-d', $value)->startOfDay();
        } catch (\Throwable) {
            return null;
        }
    }
}
