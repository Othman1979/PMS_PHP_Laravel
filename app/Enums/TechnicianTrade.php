<?php

namespace App\Enums;

enum TechnicianTrade: string
{
    use HasLabel;

    private const LABEL_PREFIX = 'Trade_';

    case Electrical = 'Electrical';
    case Mechanical = 'Mechanical';
    case GeneralMaintenance = 'GeneralMaintenance';
}
