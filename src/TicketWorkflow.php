<?php

declare(strict_types=1);

namespace Fillindev\Support;

use Fillindev\Support\Enums\TicketStatus;
use Fillindev\Support\Exceptions\InvalidTicketTransition;

final class TicketWorkflow
{
    /**
     * @return list<TicketStatus>
     */
    public static function allowed(TicketStatus $from): array
    {
        return match ($from) {
            TicketStatus::Open => [
                TicketStatus::InProgress,
                TicketStatus::WaitingCustomer,
                TicketStatus::Resolved,
                TicketStatus::Closed,
            ],
            TicketStatus::InProgress => [
                TicketStatus::WaitingCustomer,
                TicketStatus::Resolved,
                TicketStatus::Closed,
            ],
            TicketStatus::WaitingCustomer => [
                TicketStatus::InProgress,
                TicketStatus::Resolved,
                TicketStatus::Closed,
            ],
            TicketStatus::Resolved => [
                TicketStatus::Open,
                TicketStatus::Closed,
            ],
            TicketStatus::Closed => [
                TicketStatus::Open,
            ],
        };
    }

    public static function assertCan(TicketStatus $from, TicketStatus $to): void
    {
        if (! in_array($to, self::allowed($from), true)) {
            throw InvalidTicketTransition::status($from, $to);
        }
    }
}
