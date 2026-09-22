<?php

declare(strict_types=1);

namespace Fillindev\Support\Listeners;

use Fillindev\Support\Contracts\SupportableUser;
use Fillindev\Support\Enums\TicketEventType;
use Fillindev\Support\Events\MessagePosted;
use Fillindev\Support\Events\TicketAssigned;
use Fillindev\Support\Events\TicketCategoryChanged;
use Fillindev\Support\Events\TicketClosed;
use Fillindev\Support\Events\TicketCreated;
use Fillindev\Support\Events\TicketPriorityChanged;
use Fillindev\Support\Events\TicketReopened;
use Fillindev\Support\Events\TicketStatusChanged;
use Fillindev\Support\Models\Ticket;
use Fillindev\Support\SupportIds;
use Illuminate\Contracts\Events\Dispatcher;

class RecordTicketHistory
{
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
            MessagePosted::class => 'messagePosted',
        ];
    }

    public function created(TicketCreated $event): void
    {
        $this->record(
            $event->ticket,
            $event->actor,
            TicketEventType::Created,
            newValue: $event->ticket->status->value,
            meta: ['message_id' => $event->message->id],
        );
    }

    public function assigned(TicketAssigned $event): void
    {
        $this->record(
            $event->ticket,
            $event->actor,
            TicketEventType::Assigned,
            oldValue: $event->previousAssigneeId === null ? null : (string) $event->previousAssigneeId,
            newValue: $event->ticket->assignee_id === null ? null : (string) $event->ticket->assignee_id,
        );
    }

    public function statusChanged(TicketStatusChanged $event): void
    {
        $this->record(
            $event->ticket,
            $event->actor,
            TicketEventType::StatusChanged,
            oldValue: $event->from->value,
            newValue: $event->to->value,
        );
    }

    public function priorityChanged(TicketPriorityChanged $event): void
    {
        $this->record(
            $event->ticket,
            $event->actor,
            TicketEventType::PriorityChanged,
            oldValue: $event->from->value,
            newValue: $event->to->value,
        );
    }

    public function categoryChanged(TicketCategoryChanged $event): void
    {
        $this->record(
            $event->ticket,
            $event->actor,
            TicketEventType::CategoryChanged,
            oldValue: $event->fromCategoryId === null ? null : (string) $event->fromCategoryId,
            newValue: $event->toCategoryId === null ? null : (string) $event->toCategoryId,
        );
    }

    public function closed(TicketClosed $event): void
    {
        $this->record(
            $event->ticket,
            $event->actor,
            TicketEventType::Closed,
            oldValue: $event->from->value,
            newValue: $event->ticket->status->value,
        );
    }

    public function reopened(TicketReopened $event): void
    {
        $this->record(
            $event->ticket,
            $event->actor,
            TicketEventType::Reopened,
            oldValue: $event->from->value,
            newValue: $event->ticket->status->value,
        );
    }

    public function messagePosted(MessagePosted $event): void
    {
        $this->record(
            $event->ticket,
            $event->actor,
            TicketEventType::MessagePosted,
            newValue: (string) $event->message->id,
            meta: ['is_internal' => $event->message->is_internal],
        );
    }

    /**
     * @param  array<string, mixed>|null  $meta
     */
    private function record(
        Ticket $ticket,
        SupportableUser $actor,
        TicketEventType $type,
        ?string $oldValue = null,
        ?string $newValue = null,
        ?array $meta = null,
    ): void {
        $ticket->events()->create([
            'actor_id' => SupportIds::toInt($actor->getSupportUserId()),
            'type' => $type,
            'old_value' => $oldValue,
            'new_value' => $newValue,
            'meta' => $meta,
        ]);
    }
}
