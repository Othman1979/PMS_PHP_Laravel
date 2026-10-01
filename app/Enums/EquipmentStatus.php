<?php

namespace App\Enums;

enum EquipmentStatus: string
{
    use HasLabel;

    private const LABEL_PREFIX = 'EqStatus_';

    case Working = 'Working';
    case WorkingWithIssues = 'WorkingWithIssues';
    case Down = 'Down';
    case OutOfService = 'OutOfService';

    public function badge(): string
    {
        return match ($this) {
            self::Working => 'bg-success',
            self::WorkingWithIssues => 'bg-warning text-dark',
            self::Down => 'bg-danger',
            self::OutOfService => 'bg-dark',
        };
    }
}
