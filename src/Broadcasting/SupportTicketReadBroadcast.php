<?php

declare(strict_types=1);

namespace Fillindev\Support\Broadcasting;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Клиент или оператор открыл карточку и дочитал тред.
 */
class SupportTicketReadBroadcast implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets;

    public function __construct(
        public int $ticketId,
        public string $requesterType,
        public int $requesterId,
        public string $by,
        public ?string $requesterLastReadAt,
        public ?string $agentLastReadAt,
    ) {}

    /**
     * @return list<PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel(SupportChannels::ticket($this->ticketId)),
            new PrivateChannel(SupportChannels::user($this->requesterType, $this->requesterId)),
            new PrivateChannel(SupportChannels::QUEUE),
        ];
    }

    public function broadcastAs(): string
    {
        return 'support.ticket.read';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'ticket_id' => $this->ticketId,
            'by' => $this->by,
            'requester_last_read_at' => $this->requesterLastReadAt,
            'agent_last_read_at' => $this->agentLastReadAt,
        ];
    }
}
