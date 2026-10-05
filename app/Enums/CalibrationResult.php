<?php

namespace App\Enums;

enum CalibrationResult: string
{
    use HasLabel;

    private const LABEL_PREFIX = 'CalResult_';

    case Pass = 'Pass';
    case Adjusted = 'Adjusted';
    case Fail = 'Fail';

    public function badge(): string
    {
        return match ($this) {
            self::Pass => 'bg-success',
            self::Adjusted => 'bg-warning text-dark',
            self::Fail => 'bg-danger',
        };
    }
}
