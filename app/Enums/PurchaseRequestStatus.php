<?php

namespace App\Enums;

enum PurchaseRequestStatus: string
{
    use HasLabel;

    private const LABEL_PREFIX = 'PR_Status_';

    case PendingApproval = 'PendingApproval';
    case Approved = 'Approved';
    case Rejected = 'Rejected';
    case Received = 'Received';
    case Cancelled = 'Cancelled';

    public function badge(): string
    {
        return match ($this) {
            self::PendingApproval => 'bg-warning text-dark',
            self::Approved => 'bg-primary',
            self::Rejected => 'bg-danger',
            self::Received => 'bg-success',
            self::Cancelled => 'bg-light text-dark border',
        };
    }
}
