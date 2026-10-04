<?php

namespace App\Http\Controllers;

use App\Enums\Role;
use App\Enums\StockMovementType;
use App\Models\Department;
use App\Models\Equipment;
use App\Models\FaultCause;
use App\Models\FaultType;
use App\Models\SparePart;
use App\Models\User;
use App\Reports\ReportExporter;
use App\Reports\ReportFilters;
use App\Reports\ReportRegistry;
use App\Reports\SummaryReport;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class ReportController extends Controller
{
    public function show(Request $request, string $report = ReportRegistry::DEFAULT): View
    {
        $definition = ReportRegistry::resolve($report);
        $filters = ReportFilters::fromRequest($request, $definition->filterKeys(), $definition->defaultFrom());

        $data = [
            'report' => $definition,
            'reports' => ReportRegistry::all(),
            'filters' => $filters,
            'query' => $filters->toQuery(),
        ] + $this->lookups($filters);

        if ($definition instanceof SummaryReport) {
            return view('reports.summary', $data + $definition->build($filters));
        }

        $rows = $definition->rows($filters);

        return view('reports.show', $data + [
            'columns' => array_map(fn ($c) => $c->toArray(), $definition->columns()),
            'rows' => $rows,
            'totals' => $definition->totals($rows),
        ]);
    }

    public function export(Request $request, string $report, string $format, ReportExporter $exporter): Response
    {
        $definition = ReportRegistry::resolve($report);
        $filters = ReportFilters::fromRequest($request, $definition->filterKeys(), $definition->defaultFrom());

        return $format === 'pdf' ? $exporter->pdf($definition, $filters) : $exporter->xlsx($definition, $filters);
    }

    /** @return array<string, mixed> */
    private function lookups(ReportFilters $filters): array
    {
        return [
            'equipmentList' => $filters->has('equipment_id') ? Equipment::query()->orderBy('code')->get(['id', 'code', 'name']) : collect(),
            'departments' => $filters->has('department_id') ? Department::active()->ordered()->get() : collect(),
            'technicians' => $filters->has('technician_id') ? User::query()->where('role', Role::Technician)->where('is_active', true)->orderBy('full_name')->get(['id', 'full_name']) : collect(),
            'faultTypes' => $filters->has('fault_type_id') ? FaultType::query()->ordered()->get() : collect(),
            'faultCauses' => $filters->has('fault_cause_id') ? FaultCause::query()->ordered()->get() : collect(),
            'spareParts' => $filters->has('spare_part_id') ? SparePart::query()->orderBy('name')->get(['id', 'name']) : collect(),
            'movementTypes' => $filters->has('movement_type') ? StockMovementType::cases() : [],
        ];
    }
}
