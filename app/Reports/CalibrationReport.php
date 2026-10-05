<?php

namespace App\Reports;

use App\Models\Equipment;
use App\Models\EquipmentCalibration;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Calibration log of every measuring device: history records plus devices that have never been calibrated.
 */
class CalibrationReport extends Report
{
    public function key(): string
    {
        return 'calibration';
    }

    public function title(): string
    {
        return __('Report_Calibration');
    }

    public function filterKeys(): array
    {
        return ['from', 'to', 'department_id', 'equipment_id'];
    }

    public function defaultFrom(): null
    {
        return null;
    }

    public function defaultGroup(): ?string
    {
        return 'equipment';
    }

    public function columns(): array
    {
        return [
            Column::date('date', __('CalibrationDate')),
            Column::link('equipment', __('Equipment'), 'url'),
            Column::text('department', __('Department'), groupable: true),
            Column::badge('result', __('Result'), 'result_class', groupable: true),
            Column::text('provider', __('CalibrationProvider'), groupable: true),
            Column::date('next_due', __('NextCalibration')),
            Column::badge('current', __('CurrentStatus'), 'current_class', groupable: true),
            Column::link('certificate', __('CalibrationCertificate'), 'certificate_url'),
            Column::text('recorded_by', __('User'), visible: false),
            Column::text('notes', __('Note'), visible: false),
        ];
    }

    public function rows(ReportFilters $filters): Collection
    {
        $devices = Equipment::query()->measuringDevices()
            ->with('department')
            ->when($filters->departmentId, fn (Builder $q) => $q->where('department_id', $filters->departmentId))
            ->when($filters->equipmentId, fn (Builder $q) => $q->whereKey($filters->equipmentId))
            ->get()->keyBy('id');

        $records = EquipmentCalibration::query()
            ->with('recordedBy')
            ->whereIn('equipment_id', $devices->keys())
            ->when($filters->from, fn (Builder $q) => $q->where('calibrated_at', '>=', $filters->from->startOfDay()))
            ->when($filters->to, fn (Builder $q) => $q->where('calibrated_at', '<=', $filters->to->endOfDay()))
            ->orderByDesc('calibrated_at')->orderByDesc('id')
            ->get();

        $rows = $records->map(fn (EquipmentCalibration $c) => $this->row($devices[$c->equipment_id], $c));

        $neverCalibrated = $devices->filter(fn (Equipment $e) => ! $records->contains('equipment_id', $e->id) && $e->last_calibration_date === null)
            ->map(fn (Equipment $e) => $this->row($e, null));

        return $rows->concat($neverCalibrated)->values();
    }

    /** @return array<string, mixed> */
    private function row(Equipment $e, ?EquipmentCalibration $c): array
    {
        $status = $e->calibrationStatus();

        return [
            'date' => $c?->calibrated_at->format('Y-m-d'),
            'equipment' => $e->code.' - '.$e->name,
            'url' => route('equipment.show', $e),
            'department' => $e->department?->localized_name ?? '-',
            'result' => $c?->result->label() ?? __('Calibration_Missing'),
            'result_class' => $c?->result->badge() ?? 'bg-warning text-dark',
            'provider' => $c?->provider ?? '-',
            'next_due' => ($c?->next_due_date ?? $e->next_calibration_date)?->format('Y-m-d'),
            'current' => $status->label(),
            'current_class' => $status->badge(),
            'certificate' => $c?->certificate_number ?? ($c?->certificate_url ? __('ViewDocument') : null),
            'certificate_url' => $c?->certificate_url,
            'recorded_by' => $c?->recordedBy?->full_name ?? '-',
            'notes' => $c?->notes ?? '',
        ];
    }
}
