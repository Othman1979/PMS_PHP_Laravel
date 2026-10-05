<?php

namespace App\Enums;

enum CalibrationStatus: string
{
    use HasLabel;

    private const LABEL_PREFIX = 'Calibration_';

    case NotRequired = 'NotRequired';
    case Missing = 'Missing';
    case Valid = 'Valid';
    case DueSoon = 'DueSoon';
    case Expired = 'Expired';

    public function badge(): string
    {
        return match ($this) {
            self::NotRequired => 'bg-secondary',
            self::Missing => 'bg-warning text-dark',
            self::Valid => 'bg-success',
            self::DueSoon => 'bg-warning text-dark',
            self::Expired => 'bg-danger',
        };
    }

    public function needsAttention(): bool
    {
        return in_array($this, [self::Missing, self::DueSoon, self::Expired], true);
    }
}
