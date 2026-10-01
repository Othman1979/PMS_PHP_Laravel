<?php

namespace App\Enums;

enum DepartmentConfirmation: string
{
    use HasLabel;

    private const LABEL_PREFIX = 'Confirmation_';

    case Pending = 'Pending';
    case Resolved = 'Resolved';
    case NotResolved = 'NotResolved';
}
