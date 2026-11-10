<?php

declare(strict_types=1);

namespace Fillindev\Support\Http;

use Fillindev\Support\Contracts\SupportableUser;
use Fillindev\Support\Contracts\SupportAttachmentStore;
use Fillindev\Support\Contracts\SupportUserResolver;
use Fillindev\Support\Enums\TicketStatus;
use Fillindev\Support\Http\Resources\TicketMessageResource;
use Fillindev\Support\Models\Ticket;
use Fillindev\Support\Models\TicketMessage;
use Fillindev\Support\Services\MessageService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;

/**
 * Готовит тикет к JSON: текст первого публичного сообщения, автор и вложения.
 */
final class TicketPresenter
{
    public function __construct(
        private readonly SupportUserResolver $users,
        private readonly SupportAttachmentStore $attachments,
        private readonly MessageService $messages,
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
            ->human()
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
        $latest = $ticket->messages()
            ->when($publicMessagesOnly, fn (Builder $query) => $query->public())
            ->latest('id')
            ->first();

        $messages = $latest instanceof TicketMessage
            ? $this->messages->around(
                $latest,
                20,
                true,
                true,
                $publicMessagesOnly,
            )
            : new Collection;

        $this->hydrateMessages($messages);

        $pinned = $ticket->messages()
            ->pinned()
            ->when($publicMessagesOnly, fn (Builder $query) => $query->public())
            ->orderBy('id')
            ->get();
        $this->hydrateMessages($pinned);

        $ticket->setRelation('messages', $messages);
        $ticket->setAttribute('pinned_messages', $pinned);
        $ticket->loadMissing('category');
        $ticket->setAttribute(
            'body',
            TicketMessage::query()
                ->public()
                ->human()
                ->where('ticket_id', $ticket->id)
                ->orderBy('id')
                ->value('body'),
        );
        $this->applyOperators([$ticket]);

        return $ticket;
    }

    /**
     * @param  iterable<TicketMessage>  $messages
     */
    public function hydrateMessages(iterable $messages): void
    {
        foreach ($messages as $message) {
            if (! $message instanceof TicketMessage) {
                continue;
            }

            $message->setAttribute('author_payload', $this->authorPayload($message));
            $message->setAttribute('attachment_payload', $this->attachments->list($message));
        }
    }

    /**
     * @param  iterable<Ticket>  $tickets
     */
    public function applyOperators(iterable $tickets): void
    {
        $models = new Collection(collect($tickets)->filter(fn ($ticket) => $ticket instanceof Ticket)->values()->all());

        if ($models->isEmpty()) {
            return;
        }

        $models->loadMissing('operators');
        $names = [];

        foreach ($models as $ticket) {
            $ticket->setAttribute('operators_payload', $ticket->operators->map(function ($operator) use (&$names): array {
                $key = $operator->operator_type.':'.$operator->operator_id;
                if (! array_key_exists($key, $names)) {
                    $user = $this->users->resolve((string) $operator->operator_type, $operator->operator_id);
                    $names[$key] = $user instanceof SupportableUser ? $user->getSupportDisplayName() : null;
                }

                return [
                    'type' => $operator->operator_type,
                    'id' => (int) $operator->operator_id,
                    'name' => $names[$key],
                ];
            })->values()->all());
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function messagePayload(TicketMessage $message): array
    {
        $this->hydrateMessages([$message]);

        $request = app()->bound('request') ? request() : Request::create('/');

        return json_decode(
            json_encode(TicketMessageResource::make($message)->resolve($request), JSON_THROW_ON_ERROR),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );
    }

    /**
     * @return array{type: string, id: int|string, name: string, email: string|null, phone_code: string|null, phone: string|null}|null
     */
    private function authorPayload(TicketMessage $message): ?array
    {
        if ($message->author_id === null || $message->author_type === null || $message->author_type === '') {
            return null;
        }

        $author = $this->users->resolve((string) $message->author_type, $message->author_id);

        if (! $author instanceof SupportableUser) {
            return null;
        }

        return [
            'type' => $author->getSupportUserType(),
            'id' => $author->getSupportUserId(),
            'name' => $author->getSupportDisplayName(),
            'email' => $author->getSupportEmail(),
            'phone_code' => $author->getSupportPhoneCode(),
            'phone' => $author->getSupportPhone(),
        ];
    }
}
