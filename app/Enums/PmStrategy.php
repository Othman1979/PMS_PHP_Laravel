<?php

namespace App\Enums;

enum PmStrategy: string
{
    use HasLabel;

    private const LABEL_PREFIX = 'Strategy_';

    case TimeBased = 'TimeBased';
    case UsageBased = 'UsageBased';
    case ConditionBased = 'ConditionBased';
    case FailureFinding = 'FailureFinding';
}
