<?php

declare(strict_types=1);

namespace Fillindev\Support\Events;

use Fillindev\Support\Models\Ticket;

final class TicketRead
{
    public function __construct(
        public Ticket $ticket,
        public bool $byAgent,
    ) {}
}
