<?php

declare(strict_types=1);

namespace Fillindev\Support\Broadcasting;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Счётчик непрочитанных изменился или кто-то отметил сообщение прочитанным.
 * Уходит автору и всем операторам.
 */
class SupportUnreadBroadcast implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets;

    /**
     * @param  array{messages: int, tickets: int}  $requester
     * @param  array{messages: int, tickets: int}  $agent
     */
    public function __construct(
        public int $ticketId,
        public string $requesterType,
        public int $requesterId,
        public array $requester,
        public array $agent,
        public ?string $requesterLastMessageAt,
        public ?string $agentLastMessageAt,
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
        return 'support.unread.updated';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'ticket_id' => $this->ticketId,
            'requester_type' => $this->requesterType,
            'requester_id' => $this->requesterId,
            'requester' => $this->requester,
            'agent' => $this->agent,
            'requester_last_message_at' => $this->requesterLastMessageAt,
            'agent_last_message_at' => $this->agentLastMessageAt,
            'requester_last_read_at' => $this->requesterLastReadAt,
            'agent_last_read_at' => $this->agentLastReadAt,
        ];
    }
}
