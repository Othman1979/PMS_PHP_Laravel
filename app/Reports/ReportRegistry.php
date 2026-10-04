<?php

namespace App\Reports;

use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class ReportRegistry
{
    /** @var list<class-string<Report>> */
    private const REPORTS = [
        RequestsReport::class,
        SummaryReport::class,
        TechniciansReport::class,
        CostsReport::class,
        EquipmentCostReport::class,
        FaultsReport::class,
        TopFailingReport::class,
        StockReport::class,
    ];

    public const DEFAULT = 'requests';

    /** @return list<Report> */
    public static function all(): array
    {
        return array_map(fn (string $class) => new $class, self::REPORTS);
    }

    public static function resolve(string $key): Report
    {
        foreach (self::all() as $report) {
            if ($report->key() === $key) {
                return $report;
            }
        }

        throw new NotFoundHttpException;
    }
}
