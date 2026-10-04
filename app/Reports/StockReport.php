<?php

namespace App\Reports;

use App\Models\StockMovement;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class StockReport extends Report
{
    public function key(): string
    {
        return 'stock';
    }

    public function title(): string
    {
        return __('Report_Stock');
    }

    public function filterKeys(): array
    {
        return ['from', 'to', 'spare_part_id', 'movement_type', 'department_id', 'equipment_id'];
    }

    public function defaultGroup(): ?string
    {
        return 'type';
    }

    public function columns(): array
    {
        return [
            Column::date('date', __('Date')),
            Column::text('part', __('SparePart'), groupable: true),
            Column::text('part_number', __('PartNumber'), visible: false),
            Column::badge('type', __('MovementType'), 'type_class'),
            Column::int('quantity', __('Quantity'), total: true),
            Column::money('unit_cost', __('UnitCost'), total: false),
            Column::money('value', __('Value')),
            Column::int('balance_after', __('BalanceAfter')),
            Column::link('request_number', __('RequestNumber'), 'request_url'),
            Column::text('equipment', __('Equipment'), groupable: true),
            Column::text('department', __('Department'), groupable: true),
            Column::text('receipt', __('GoodsReceipt'), visible: false),
            Column::text('user', __('User'), groupable: true, visible: false),
            Column::text('note', __('Note'), visible: false),
        ];
    }

    public function rows(ReportFilters $filters): Collection
    {
        return StockMovement::query()
            ->with(['sparePart', 'user', 'goodsReceipt', 'maintenanceRequest.equipment', 'maintenanceRequest.department'])
            ->when($filters->from, fn (Builder $q) => $q->where('date', '>=', $filters->from->startOfDay()))
            ->when($filters->to, fn (Builder $q) => $q->where('date', '<=', $filters->to->endOfDay()))
            ->when($filters->sparePartId, fn (Builder $q) => $q->where('spare_part_id', $filters->sparePartId))
            ->when($filters->movementType, fn (Builder $q) => $q->where('type', $filters->movementType))
            ->when($filters->departmentId, fn (Builder $q) => $q->whereHas('maintenanceRequest', fn (Builder $r) => $r->where('department_id', $filters->departmentId)))
            ->when($filters->equipmentId, fn (Builder $q) => $q->whereHas('maintenanceRequest', fn (Builder $r) => $r->where('equipment_id', $filters->equipmentId)))
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->get()
            ->map(fn (StockMovement $m) => [
                'date' => $m->date->format('Y-m-d'),
                'part' => $m->sparePart?->name ?? '-',
                'part_number' => $m->sparePart?->part_number ?? '-',
                'type' => $m->type->label(),
                'type_class' => $m->type->badge(),
                'quantity' => (int) $m->quantity,
                'unit_cost' => (float) $m->unit_cost,
                'value' => round(abs((int) $m->quantity) * (float) $m->unit_cost, 2),
                'balance_after' => (int) $m->balance_after,
                'request_number' => $m->maintenanceRequest?->request_number,
                'request_url' => $m->maintenanceRequest ? route('requests.show', $m->maintenanceRequest) : null,
                'equipment' => $m->maintenanceRequest?->equipment?->name ?? '-',
                'department' => $m->maintenanceRequest?->department?->localized_name ?? '-',
                'receipt' => $m->goodsReceipt?->number ?? '-',
                'user' => $m->user?->full_name ?? '-',
                'note' => $m->note ?? '',
            ])
            ->values();
    }
}
