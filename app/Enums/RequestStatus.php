<?php

namespace App\Enums;

enum RequestStatus: string
{
    use HasLabel;

    private const LABEL_PREFIX = 'Status_';

    case New = 'New';
    case UnderReview = 'UnderReview';
    case Assigned = 'Assigned';
    case Accepted = 'Accepted';
    case InProgress = 'InProgress';
    case WaitingParts = 'WaitingParts';
    case Completed = 'Completed';
    case Closed = 'Closed';
    case Reopened = 'Reopened';
    case Cancelled = 'Cancelled';

    public function badge(): string
    {
        return match ($this) {
            self::New => 'bg-primary',
            self::UnderReview => 'bg-info text-dark',
            self::Assigned => 'bg-secondary',
            self::Accepted => 'bg-primary-subtle text-primary-emphasis border border-primary',
            self::InProgress => 'bg-warning text-dark',
            self::WaitingParts => 'bg-orange',
            self::Completed => 'bg-success',
            self::Closed => 'bg-dark',
            self::Reopened => 'bg-danger',
            self::Cancelled => 'bg-light text-dark border',
        };
    }

    /** @return list<self> */
    public static function closed(): array
    {
        return [self::Completed, self::Closed, self::Cancelled];
    }

    /** @return list<string> */
    public static function closedValues(): array
    {
        return array_map(fn (self $s) => $s->value, self::closed());
    }
}
