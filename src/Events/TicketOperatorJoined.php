<?php

declare(strict_types=1);

namespace Fillindev\Support\Events;

use Fillindev\Support\Contracts\SupportableUser;
use Fillindev\Support\Models\Ticket;

final class TicketOperatorJoined
{
    public function __construct(
        public Ticket $ticket,
        public SupportableUser $operator,
        public SupportableUser $actor,
    ) {}
}
