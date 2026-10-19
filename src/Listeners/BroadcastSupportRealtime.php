<?php

declare(strict_types=1);

namespace Fillindev\Support\Listeners;

use Fillindev\Support\Broadcasting\SupportBroadcastReason;
use Fillindev\Support\Broadcasting\SupportRealtime;
use Fillindev\Support\Events\TicketAssigned;
use Fillindev\Support\Events\TicketCategoryChanged;
use Fillindev\Support\Events\TicketClosed;
use Fillindev\Support\Events\TicketCreated;
use Fillindev\Support\Events\TicketPriorityChanged;
use Fillindev\Support\Events\TicketRead;
use Fillindev\Support\Events\TicketReopened;
use Fillindev\Support\Events\TicketStatusChanged;
use Illuminate\Contracts\Events\Dispatcher;

class BroadcastSupportRealtime
{
    public function __construct(
        private readonly SupportRealtime $realtime,
    ) {}

    /**
     * @return array<class-string, string>
     */
    public function subscribe(Dispatcher $events): array
    {
        return [
            TicketCreated::class => 'created',
            TicketAssigned::class => 'assigned',
            TicketStatusChanged::class => 'statusChanged',
            TicketPriorityChanged::class => 'priorityChanged',
            TicketCategoryChanged::class => 'categoryChanged',
            TicketClosed::class => 'closed',
            TicketReopened::class => 'reopened',
            TicketRead::class => 'read',
        ];
    }

    public function created(TicketCreated $event): void
    {
        $this->realtime->ticket($event->ticket, SupportBroadcastReason::Created);
    }

    public function assigned(TicketAssigned $event): void
    {
        $this->realtime->ticket($event->ticket, SupportBroadcastReason::Assigned);
    }

    public function statusChanged(TicketStatusChanged $event): void
    {
        $this->realtime->ticket($event->ticket, SupportBroadcastReason::Status);
    }

    public function priorityChanged(TicketPriorityChanged $event): void
    {
        $this->realtime->ticket($event->ticket, SupportBroadcastReason::Priority);
    }

    public function categoryChanged(TicketCategoryChanged $event): void
    {
        $this->realtime->ticket($event->ticket, SupportBroadcastReason::Category);
    }

    public function closed(TicketClosed $event): void
    {
        $this->realtime->ticket($event->ticket, SupportBroadcastReason::Closed);
    }

    public function reopened(TicketReopened $event): void
    {
        $this->realtime->ticket($event->ticket, SupportBroadcastReason::Reopened);
    }

    public function read(TicketRead $event): void
    {
        $this->realtime->read($event->ticket, $event->byAgent);
    }
}
