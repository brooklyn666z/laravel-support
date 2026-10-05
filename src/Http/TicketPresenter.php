<?php

declare(strict_types=1);

namespace Fillindev\Support\Http;

use Fillindev\Support\Contracts\SupportableUser;
use Fillindev\Support\Contracts\SupportAttachmentStore;
use Fillindev\Support\Contracts\SupportUserResolver;
use Fillindev\Support\Enums\TicketStatus;
use Fillindev\Support\Models\Ticket;
use Fillindev\Support\Models\TicketMessage;
use Illuminate\Database\Eloquent\Builder;

/**
 * Готовит тикет к JSON: текст первого публичного сообщения, автор и вложения.
 */
final class TicketPresenter
{
    public function __construct(
        private readonly SupportUserResolver $users,
        private readonly SupportAttachmentStore $attachments,
    ) {}

    /**
     * Счётчики по той же выборке, что и список, до фильтра статуса и пагинации.
     *
     * @param  Builder<Ticket>  $query
     * @return array<string, int>
     */
    public function statusSummary(Builder $query): array
    {
        $rows = (clone $query)
            ->reorder()
            ->selectRaw('status, count(*) as aggregate')
            ->groupBy('status')
            ->get();

        $counts = [];
        foreach ($rows as $row) {
            $status = $row->getAttribute('status');
            $key = $status instanceof TicketStatus ? $status->value : (string) $status;
            $counts[$key] = (int) $row->getAttribute('aggregate');
        }

        $summary = [];
        foreach (TicketStatus::cases() as $status) {
            $summary[$status->value] = $counts[$status->value] ?? 0;
        }

        return $summary;
    }

    /**
     * @param  iterable<Ticket>  $tickets
     */
    public function applyOpeningBodies(iterable $tickets): void
    {
        $tickets = collect($tickets)->filter(fn ($ticket) => $ticket instanceof Ticket)->values();

        if ($tickets->isEmpty()) {
            return;
        }

        $bodies = TicketMessage::query()
            ->public()
            ->whereIn('ticket_id', $tickets->pluck('id')->all())
            ->orderBy('id')
            ->get(['ticket_id', 'body'])
            ->groupBy('ticket_id');

        foreach ($tickets as $ticket) {
            $ticket->setAttribute('body', $bodies->get($ticket->id)?->first()?->body);
        }
    }

    public function present(Ticket $ticket, bool $publicMessagesOnly): Ticket
    {
        $messages = $ticket->messages()
            ->when($publicMessagesOnly, fn (Builder $query) => $query->public())
            ->orderBy('id')
            ->get();

        $messages->each(function (TicketMessage $message): void {
            $message->setAttribute('author_payload', $this->authorPayload($message));
            $message->setAttribute('attachment_payload', $this->attachments->list($message));
        });

        $ticket->setRelation('messages', $messages);
        $ticket->loadMissing('category');
        $ticket->setAttribute(
            'body',
            $messages->first(fn (TicketMessage $message) => ! $message->is_internal)?->body,
        );

        return $ticket;
    }

    /**
     * @return array{id: int|string, name: string, email: string|null, phone_code: string|null, phone: string|null}|null
     */
    private function authorPayload(TicketMessage $message): ?array
    {
        if ($message->author_id === null) {
            return null;
        }

        $author = $this->users->resolve($message->author_id);

        if (! $author instanceof SupportableUser) {
            return null;
        }

        return [
            'id' => $author->getSupportUserId(),
            'name' => $author->getSupportDisplayName(),
            'email' => $author->getSupportEmail(),
            'phone_code' => $author->getSupportPhoneCode(),
            'phone' => $author->getSupportPhone(),
        ];
    }
}
