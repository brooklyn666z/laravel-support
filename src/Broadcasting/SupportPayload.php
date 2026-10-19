<?php

declare(strict_types=1);

namespace Fillindev\Support\Broadcasting;

use Fillindev\Support\Http\Resources\TicketResource;
use Fillindev\Support\Http\TicketPresenter;
use Fillindev\Support\Models\Ticket;
use Fillindev\Support\Models\TicketMessage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Снимок тикета и сообщения в том же виде, что REST-ресурсы.
 * Снимок снимается в момент отправки, чтобы очередь не зависела от хоста.
 */
final class SupportPayload
{
    /**
     * @return array<string, mixed>
     */
    public function ticket(Ticket $ticket): array
    {
        $ticket->loadMissing('category');
        $ticket->setAttribute('body', TicketMessage::query()
            ->public()
            ->where('ticket_id', $ticket->id)
            ->orderBy('id')
            ->value('body'));

        $payload = $this->plain(TicketResource::make($ticket));
        unset($payload['messages']);

        return $payload;
    }

    /**
     * @return array<string, mixed>
     */
    public function message(TicketMessage $message): array
    {
        return app(TicketPresenter::class)->messagePayload($message);
    }

    /**
     * @return array<string, mixed>
     */
    private function plain(JsonResource $resource): array
    {
        $request = app()->bound('request') ? request() : Request::create('/');

        return json_decode(
            json_encode($resource->resolve($request), JSON_THROW_ON_ERROR),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );
    }
}
