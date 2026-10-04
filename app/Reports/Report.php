<?php

namespace App\Reports;

use App\Models\MaintenanceRequest;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * A tabular report: a set of columns plus the rows produced for a given filter state.
 */
abstract class Report
{
    abstract public function key(): string;

    abstract public function title(): string;

    /** @return list<string> filter keys (subset of ReportFilters::KEYS) this report honours */
    abstract public function filterKeys(): array;

    /** @return list<Column> */
    abstract public function columns(): array;

    /** @return Collection<int, array<string, mixed>> */
    abstract public function rows(ReportFilters $filters): Collection;

    /** Lower bound of the date range when the user has not chosen one; null means "all time". */
    public function defaultFrom(): ?CarbonImmutable
    {
        return CarbonImmutable::today()->subMonth();
    }

    /** Column key the grid is grouped by when first opened, or null. */
    public function defaultGroup(): ?string
    {
        return null;
    }

    public function isSummary(): bool
    {
        return false;
    }

    /**
     * Totals row, keyed by column key, for the columns flagged `total`.
     *
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return array<string, float|int>
     */
    public function totals(Collection $rows): array
    {
        $totals = [];
        foreach ($this->columns() as $column) {
            if ($column->total) {
                $totals[$column->key] = $column->type === 'int' ? (int) $rows->sum($column->key) : round((float) $rows->sum($column->key), 2);
            }
        }

        return $totals;
    }

    protected function cost(MaintenanceRequest $request): float
    {
        return (float) $request->cost_labor + (float) $request->cost_parts;
    }

    /** @param Collection<int, MaintenanceRequest> $requests */
    protected function avgHours(Collection $requests, string $toColumn): ?float
    {
        $done = $requests->filter(fn (MaintenanceRequest $r) => $r->{$toColumn} !== null);
        if ($done->isEmpty()) {
            return null;
        }

        return round((float) $done->avg(fn (MaintenanceRequest $r) => $r->created_at->diffInMinutes($r->{$toColumn}) / 60), 1);
    }
}
