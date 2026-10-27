<?php

declare(strict_types=1);

namespace Fillindev\Support\Broadcasting;

use Fillindev\Support\Models\Ticket;
use Fillindev\Support\Models\TicketMessage;
use Illuminate\Support\Facades\Event;

final class SupportRealtime
{
    public function __construct(
        private readonly SupportPayload $payload,
    ) {}

    public function message(Ticket $ticket, TicketMessage $message): void
    {
        Event::dispatch(new SupportMessageBroadcast(
            ticketId: (int) $ticket->id,
            requesterType: (string) $ticket->requester_type,
            requesterId: (int) $ticket->requester_id,
            internal: (bool) $message->is_internal,
            message: $this->payload->message($message),
        ));
    }

    public function ticket(Ticket $ticket, SupportBroadcastReason $reason): void
    {
        Event::dispatch(new SupportTicketBroadcast(
            $reason,
            $this->payload->ticket($ticket),
        ));
    }

    public function read(Ticket $ticket, bool $byAgent, ?int $messageId = null): void
    {
        Event::dispatch(new SupportTicketReadBroadcast(
            ticketId: (int) $ticket->id,
            requesterType: (string) $ticket->requester_type,
            requesterId: (int) $ticket->requester_id,
            by: $byAgent ? 'agent' : 'requester',
            requesterLastReadAt: $ticket->requester_last_read_at?->toISOString(),
            agentLastReadAt: $ticket->agent_last_read_at?->toISOString(),
            messageId: $messageId,
        ));
    }

    /**
     * @param  array{messages: int, tickets: int}  $requester
     * @param  array{messages: int, tickets: int}  $agent
     */
    public function unread(Ticket $ticket, array $requester, array $agent): void
    {
        Event::dispatch(new SupportUnreadBroadcast(
            ticketId: (int) $ticket->id,
            requesterType: (string) $ticket->requester_type,
            requesterId: (int) $ticket->requester_id,
            requester: $requester,
            agent: $agent,
            requesterLastMessageAt: $ticket->requester_last_message_at?->toISOString(),
            agentLastMessageAt: $ticket->agent_last_message_at?->toISOString(),
            requesterLastReadAt: $ticket->requester_last_read_at?->toISOString(),
            agentLastReadAt: $ticket->agent_last_read_at?->toISOString(),
        ));
    }
}
