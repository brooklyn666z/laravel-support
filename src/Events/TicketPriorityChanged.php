<?php

declare(strict_types=1);

namespace Fillindev\Support\Events;

use Fillindev\Support\Contracts\SupportableUser;
use Fillindev\Support\Enums\TicketPriority;
use Fillindev\Support\Models\Ticket;

final class TicketPriorityChanged
{
    public function __construct(
        public Ticket $ticket,
        public TicketPriority $from,
        public TicketPriority $to,
        public SupportableUser $actor,
    ) {}
}
