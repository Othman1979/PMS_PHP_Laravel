<?php

namespace App\Reports;

use App\Models\Equipment;
use App\Models\MaintenanceRequest;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Maintenance spend per piece of equipment against its purchase price, to support
 * write-off decisions. Defaults to all time because the comparison is cumulative.
 */
class EquipmentCostReport extends Report
{
    /** Maintenance-to-price ratio (%) from which write-off is recommended. */
    public const WRITE_OFF_RATIO = 100.0;

    /** Maintenance-to-price ratio (%) from which a review is suggested. */
    public const REVIEW_RATIO = 50.0;

    /** Share (%) of the department's maintenance spend from which a review is suggested. */
    public const REVIEW_DEPARTMENT_SHARE = 40.0;

    public const VERDICT_OK = 'ok';

    public const VERDICT_REVIEW = 'review';

    public const VERDICT_WRITE_OFF = 'write_off';

    public function key(): string
    {
        return 'equipment-cost';
    }

    public function title(): string
    {
        return __('Report_EquipmentCost');
    }

    public function filterKeys(): array
    {
        return ['from', 'to', 'department_id', 'equipment_id'];
    }

    public function defaultFrom(): ?CarbonImmutable
    {
        return null;
    }

    public function columns(): array
    {
        return [
            Column::link('code', __('Code'), 'url'),
            Column::text('equipment', __('Equipment')),
            Column::text('department', __('Department'), groupable: true),
            Column::text('category', __('Category'), groupable: true, visible: false),
            Column::date('purchase_date', __('PurchaseDate')),
            Column::money('purchase_price', __('PurchasePrice')),
            Column::int('requests', __('TotalRequests'), total: true),
            Column::int('failures', __('FailureCount'), total: true),
            Column::money('cost_labor', __('LaborCost')),
            Column::money('cost_parts', __('PartsCost')),
            Column::money('cost', __('MaintenanceCost')),
            Column::money('avg_cost', __('AvgCostPerRequest'), total: false),
            Column::percent('ratio', __('CostToPriceRatio')),
            Column::percent('department_share', __('ShareOfDepartmentCost')),
            Column::date('last_request', __('LastFailure')),
            Column::badge('status', __('EquipmentStatus'), 'status_class', groupable: false),
            Column::badge('verdict', __('Recommendation'), 'verdict_class'),
        ];
    }

    public function rows(ReportFilters $filters): Collection
    {
        $requests = $filters->requests()->whereNotNull('equipment_id')->get();
        $byEquipment = $requests->groupBy('equipment_id');
        $departmentTotals = $requests->groupBy('department_id')->map(fn (Collection $g) => $g->sum(fn (MaintenanceRequest $r) => $this->cost($r)));

        $equipment = Equipment::query()->with('department')->whereIn('id', $byEquipment->keys())->get()->keyBy('id');

        return $byEquipment
            ->map(function (Collection $group, int $equipmentId) use ($equipment, $departmentTotals) {
                $item = $equipment->get($equipmentId);
                if ($item === null) {
                    return null;
                }

                $cost = round($group->sum(fn (MaintenanceRequest $r) => $this->cost($r)), 2);
                $price = $item->purchase_price !== null ? (float) $item->purchase_price : null;
                $ratio = $price !== null && $price > 0 ? round($cost / $price * 100, 1) : null;
                $deptTotal = (float) ($departmentTotals->get($item->department_id) ?? 0);
                $share = $deptTotal > 0 ? round($cost / $deptTotal * 100, 1) : 0.0;
                $verdict = self::verdict($ratio, $share);

                return [
                    'code' => $item->code,
                    'url' => route('equipment.show', $item),
                    'equipment' => $item->name,
                    'department' => $item->department?->localized_name ?? '-',
                    'category' => $item->category->label(),
                    'purchase_date' => $item->purchase_date?->format('Y-m-d'),
                    'purchase_price' => $price,
                    'requests' => $group->count(),
                    'failures' => $group->filter(fn (MaintenanceRequest $r) => ! $r->is_preventive)->count(),
                    'cost_labor' => round($group->sum(fn (MaintenanceRequest $r) => (float) $r->cost_labor), 2),
                    'cost_parts' => round($group->sum(fn (MaintenanceRequest $r) => (float) $r->cost_parts), 2),
                    'cost' => $cost,
                    'avg_cost' => round($cost / $group->count(), 2),
                    'ratio' => $ratio,
                    'department_share' => $share,
                    'last_request' => $group->max('created_at')?->format('Y-m-d'),
                    'status' => $item->status->label(),
                    'status_class' => $item->status->badge(),
                    'verdict' => __('Verdict_'.$verdict),
                    'verdict_code' => $verdict,
                    'verdict_class' => match ($verdict) {
                        self::VERDICT_WRITE_OFF => 'bg-danger',
                        self::VERDICT_REVIEW => 'bg-warning text-dark',
                        default => 'bg-success',
                    },
                ];
            })
            ->filter()
            ->sortBy([['ratio', 'desc'], ['cost', 'desc']])
            ->values();
    }

    public static function verdict(?float $ratio, float $departmentShare): string
    {
        if ($ratio !== null && $ratio >= self::WRITE_OFF_RATIO) {
            return self::VERDICT_WRITE_OFF;
        }
        if (($ratio !== null && $ratio >= self::REVIEW_RATIO) || $departmentShare >= self::REVIEW_DEPARTMENT_SHARE) {
            return self::VERDICT_REVIEW;
        }

        return self::VERDICT_OK;
    }
}
