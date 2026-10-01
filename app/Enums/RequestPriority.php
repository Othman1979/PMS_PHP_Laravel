<?php

namespace App\Enums;

enum RequestPriority: string
{
    use HasLabel;

    private const LABEL_PREFIX = 'Priority_';

    case Scheduled = 'Scheduled';
    case Normal = 'Normal';
    case Urgent = 'Urgent';
    case Critical = 'Critical';

    public function badge(): string
    {
        return match ($this) {
            self::Critical => 'bg-danger',
            self::Urgent => 'bg-warning text-dark',
            self::Normal => 'bg-info text-dark',
            self::Scheduled => 'bg-secondary',
        };
    }

    public function rank(): int
    {
        return match ($this) {
            self::Scheduled => 0,
            self::Normal => 1,
            self::Urgent => 2,
            self::Critical => 3,
        };
    }

    /** SQL expression ordering priorities from lowest to highest. */
    public static function orderSql(string $column = 'priority'): string
    {
        return "CASE {$column} WHEN 'Critical' THEN 3 WHEN 'Urgent' THEN 2 WHEN 'Normal' THEN 1 ELSE 0 END";
    }
}
