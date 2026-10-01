<?php

namespace App\Enums;

enum StockMovementType: string
{
    use HasLabel;

    private const LABEL_PREFIX = 'Movement_';

    case Receipt = 'Receipt';
    case Issue = 'Issue';
    case Adjustment = 'Adjustment';

    public function badge(): string
    {
        return match ($this) {
            self::Receipt => 'bg-success',
            self::Issue => 'bg-danger',
            self::Adjustment => 'bg-secondary',
        };
    }
}
