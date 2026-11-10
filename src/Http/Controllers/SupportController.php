<?php

declare(strict_types=1);

namespace Fillindev\Support\Http\Controllers;

use Fillindev\Support\Contracts\SupportableUser;
use Fillindev\Support\Exceptions\InvalidTicketTransition;
use Fillindev\Support\Http\Requests\IndexMessagesRequest;
use Fillindev\Support\Http\Requests\PinMessageRequest;
use Fillindev\Support\Http\Resources\TicketMessageResource;
use Fillindev\Support\Http\Resources\TicketResource;
use Fillindev\Support\Http\TicketPresenter;
use Fillindev\Support\Models\Ticket;
use Fillindev\Support\Models\TicketMessage;
use Fillindev\Support\Services\MessageService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Routing\Controller;
use InvalidArgumentException;

abstract class SupportController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        protected readonly TicketPresenter $presenter,
        protected readonly MessageService $messages,
    ) {}

    protected function actor(): SupportableUser
    {
        $user = request()->user();

        if (! $user instanceof SupportableUser) {
            abort(403);
        }

        return $user;
    }

    protected function ticketResponse(Ticket $ticket, bool $publicMessagesOnly, int $status = 200): JsonResponse
    {
        return (new TicketResource($this->presenter->present($ticket, $publicMessagesOnly)))
            ->response()
            ->setStatusCode($status);
    }

    protected function messageOnTicket(Ticket $ticket, TicketMessage $message, bool $publicOnly): TicketMessage
    {
        if ((int) $message->ticket_id !== (int) $ticket->id) {
            abort(404);
        }

        if ($publicOnly && $message->is_internal) {
            abort(404);
        }

        return $message;
    }

    protected function messagesAroundResponse(
        IndexMessagesRequest $request,
        Ticket $ticket,
        TicketMessage $message,
        bool $publicOnly,
    ): JsonResponse {
        $message = $this->messageOnTicket($ticket, $message, $publicOnly);
        $page = $this->messages->around(
            $message,
            $request->integer('per_page', 20),
            $request->boolean('prev', true),
            $request->boolean('next', true),
            $publicOnly,
        );
        $this->presenter->hydrateMessages($page);

        return TicketMessageResource::collection($page)->response();
    }

    protected function pinMessageResponse(
        PinMessageRequest $request,
        Ticket $ticket,
        TicketMessage $message,
        bool $publicOnly,
    ): JsonResponse {
        $message = $this->messageOnTicket($ticket, $message, $publicOnly);
        $message = $this->messages->setPinned($message, $request->boolean('is_pinned', true));
        $this->presenter->hydrateMessages([$message]);

        return TicketMessageResource::make($message)->response();
    }

    /**
     * @return list<UploadedFile>
     */
    protected function uploadedFiles(FormRequest $request): array
    {
        $files = $request->file('attachments');

        if ($files === null) {
            return [];
        }

        return is_array($files) ? array_values($files) : [$files];
    }

    protected function attempt(callable $callback): JsonResponse
    {
        try {
            return $callback();
        } catch (InvalidArgumentException|InvalidTicketTransition $exception) {
            return response()->json([
                'errors' => [$exception->getMessage()],
            ], 422);
        }
    }
}
