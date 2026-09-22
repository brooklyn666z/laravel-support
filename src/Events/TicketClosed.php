<?php

declare(strict_types=1);

namespace Fillindev\Support\Events;

use Fillindev\Support\Contracts\SupportableUser;
use Fillindev\Support\Enums\TicketStatus;
use Fillindev\Support\Models\Ticket;

final class TicketClosed
{
    public function __construct(
        public Ticket $ticket,
        public TicketStatus $from,
        public SupportableUser $actor,
    ) {}
}
