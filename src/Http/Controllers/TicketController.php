<?php

declare(strict_types=1);

namespace Fillindev\Support\Http\Controllers;

use Fillindev\Support\Broadcasting\SupportRealtime;
use Fillindev\Support\Contracts\SupportAttachmentStore;
use Fillindev\Support\Enums\TicketPriority;
use Fillindev\Support\Http\Requests\StoreMessageRequest;
use Fillindev\Support\Http\Requests\StoreTicketRequest;
use Fillindev\Support\Http\Resources\TicketResource;
use Fillindev\Support\Http\TicketPresenter;
use Fillindev\Support\Models\Ticket;
use Fillindev\Support\Models\TicketCategory;
use Fillindev\Support\Services\MessageService;
use Fillindev\Support\Services\TicketService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use OpenApi\Attributes as OA;

#[OA\Tag(
    name: 'Support Tickets',
    description: 'Обращения клиента в техподдержку. Видны только свои тикеты. super admin проходит без этой проверки.',
)]
class TicketController extends SupportController
{
    public function __construct(
        TicketPresenter $presenter,
        private readonly TicketService $tickets,
        private readonly MessageService $messages,
        private readonly SupportAttachmentStore $attachments,
        private readonly SupportRealtime $realtime,
    ) {
        parent::__construct($presenter);
    }

    #[OA\Get(
        path: '/support/categories',
        tags: ['Support Tickets'],
        summary: 'Категории обращений',
        description: 'Только активные категории, по полю sort.',
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: 'OK',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/SupportCategory')),
                    ],
                ),
            ),
            new OA\Response(response: 401, description: 'Unauthorized'),
            new OA\Response(response: 403, description: 'Нет доступа'),
        ],
    )]
    public function categories(): JsonResponse
    {
        $this->authorize('viewAny', Ticket::class);

        return response()->json([
            'data' => TicketCategory::query()->active()->orderBy('sort')->get(['id', 'code', 'name']),
        ]);
    }

    #[OA\Get(
        path: '/support/tickets',
        tags: ['Support Tickets'],
        summary: 'Список своих обращений',
        description: 'По 15 записей. В элементах нет messages. summary считает все свои обращения, страница на него не влияет.',
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', minimum: 1)),
        ],
        responses: [
            new OA\Response(response: 200, description: 'OK', content: new OA\JsonContent(ref: '#/components/schemas/TicketPage')),
            new OA\Response(response: 401, description: 'Unauthorized'),
            new OA\Response(response: 403, description: 'Нет доступа'),
        ],
    )]
    public function index(): JsonResponse
    {
        $this->authorize('viewAny', Ticket::class);

        $base = $this->ownTickets();
        $page = (clone $base)->with('category')->latest('id')->paginate(15);
        $this->presenter->applyOpeningBodies($page->items());

        return TicketResource::collection($page)->additional([
            'summary' => $this->presenter->statusSummary($base),
        ])->response();
    }

    #[OA\Get(
        path: '/support/tickets/unread',
        tags: ['Support Tickets'],
        summary: 'Непрочитанные сообщения',
        description: 'Клиент: публичные ответы поддержки новее requester_last_read_at, только в своих обращениях. Оператор (handle): сообщения автора обращения новее agent_last_read_at по всей очереди. Внутренние заметки не входят. messages — число таких сообщений, tickets — число обращений, где они есть.',
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: 'OK',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(
                            property: 'data',
                            properties: [
                                new OA\Property(property: 'messages', type: 'integer', example: 2),
                                new OA\Property(property: 'tickets', type: 'integer', example: 1),
                            ],
                            type: 'object',
                        ),
                    ],
                ),
            ),
            new OA\Response(response: 401, description: 'Unauthorized'),
            new OA\Response(response: 403, description: 'Нет доступа'),
        ],
    )]
    public function unread(): JsonResponse
    {
        $this->authorize('viewAny', Ticket::class);

        $actor = $this->actor();
        $data = Gate::forUser($actor)->allows('handle', Ticket::class)
            ? $this->tickets->unreadForResponder()
            : $this->tickets->unreadFor($actor);

        return response()->json([
            'data' => $data,
        ]);
    }

    #[OA\Post(
        path: '/support/tickets',
        tags: ['Support Tickets'],
        summary: 'Создать обращение',
        description: 'Без файлов — application/json. С файлами — multipart/form-data. Приоритет по умолчанию normal. В ответе есть messages, первое сообщение содержит вложения.',
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: [
                new OA\MediaType(mediaType: 'application/json', schema: new OA\Schema(ref: '#/components/schemas/StoreTicketRequest')),
                new OA\MediaType(mediaType: 'multipart/form-data', schema: new OA\Schema(ref: '#/components/schemas/StoreTicketRequest')),
            ],
        ),
        responses: [
            new OA\Response(response: 201, description: 'Created', content: new OA\JsonContent(ref: '#/components/schemas/TicketData')),
            new OA\Response(response: 401, description: 'Unauthorized'),
            new OA\Response(response: 403, description: 'Нет доступа'),
            new OA\Response(ref: '#/components/responses/SupportUnprocessable', response: 422),
        ],
    )]
    public function store(StoreTicketRequest $request): JsonResponse
    {
        $this->authorize('create', Ticket::class);

        return $this->attempt(function () use ($request) {
            $categoryId = $request->validated('category_id');

            $ticket = $this->tickets->create(
                requester: $this->actor(),
                subject: $request->validated('subject'),
                body: $request->validated('body'),
                categoryId: $categoryId === null ? null : (int) $categoryId,
                priority: $request->enum('priority', TicketPriority::class) ?? TicketPriority::Normal,
            );

            $opening = $ticket->messages()->oldest('id')->first();
            if ($opening) {
                $this->attachments->store($opening, $this->uploadedFiles($request));
                $this->realtime->message($ticket, $opening);
            }

            return $this->ticketResponse($ticket, true, 201);
        });
    }

    #[OA\Get(
        path: '/support/tickets/{ticket}',
        tags: ['Support Tickets'],
        summary: 'Карточка обращения',
        description: 'Отмечает обращения прочитанным клиентом. В messages только публичные сообщения, с автором и вложениями.',
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'ticket', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'OK', content: new OA\JsonContent(ref: '#/components/schemas/TicketData')),
            new OA\Response(response: 401, description: 'Unauthorized'),
            new OA\Response(response: 403, description: 'Чужое обращение'),
            new OA\Response(response: 404, description: 'Не найдено'),
        ],
    )]
    public function show(int $ticket): JsonResponse
    {
        $ticket = $this->ownTicket($ticket);
        $this->authorize('view', $ticket);
        $ticket = $this->tickets->markRead($ticket, false);

        return $this->ticketResponse($ticket, true);
    }

    #[OA\Post(
        path: '/support/tickets/{ticket}/messages',
        tags: ['Support Tickets'],
        summary: 'Ответ клиента',
        description: 'Сообщение всегда публичное. В закрытое обращение писать нельзя. Ответ — карточка со всеми публичными сообщениями.',
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'ticket', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: [
                new OA\MediaType(mediaType: 'application/json', schema: new OA\Schema(ref: '#/components/schemas/StoreClientMessageRequest')),
                new OA\MediaType(mediaType: 'multipart/form-data', schema: new OA\Schema(ref: '#/components/schemas/StoreClientMessageRequest')),
            ],
        ),
        responses: [
            new OA\Response(response: 201, description: 'Created', content: new OA\JsonContent(ref: '#/components/schemas/TicketData')),
            new OA\Response(response: 401, description: 'Unauthorized'),
            new OA\Response(response: 403, description: 'Чужое обращение'),
            new OA\Response(response: 404, description: 'Не найдено'),
            new OA\Response(ref: '#/components/responses/SupportUnprocessable', response: 422),
        ],
    )]
    public function storeMessage(StoreMessageRequest $request, int $ticket): JsonResponse
    {
        $ticket = $this->ownTicket($ticket);
        $this->authorize('reply', $ticket);

        return $this->attempt(function () use ($request, $ticket) {
            $message = $this->messages->post($ticket, $this->actor(), $request->validated('body'), false);
            $this->attachments->store($message, $this->uploadedFiles($request));
            $this->realtime->message($ticket, $message);

            return $this->ticketResponse($ticket, true, 201);
        });
    }

    #[OA\Post(
        path: '/support/tickets/{ticket}/close',
        tags: ['Support Tickets'],
        summary: 'Закрыть своё обращение',
        description: 'Тело запроса не нужно. Если обращение уже закрыто, статус не меняется и ответ остаётся 200.',
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'ticket', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'OK', content: new OA\JsonContent(ref: '#/components/schemas/TicketData')),
            new OA\Response(response: 401, description: 'Unauthorized'),
            new OA\Response(response: 403, description: 'Чужое обращение'),
            new OA\Response(response: 404, description: 'Не найдено'),
            new OA\Response(ref: '#/components/responses/SupportUnprocessable', response: 422),
        ],
    )]
    public function close(int $ticket): JsonResponse
    {
        $ticket = $this->ownTicket($ticket);
        $this->authorize('reply', $ticket);

        return $this->attempt(function () use ($ticket) {
            $ticket = $this->tickets->close($ticket, $this->actor());

            return $this->ticketResponse($ticket, true);
        });
    }

    /**
     * @return \Illuminate\Database\Eloquent\Builder<Ticket>
     */
    private function ownTickets()
    {
        $actor = $this->actor();

        return Ticket::query()->forRequester($actor->getSupportUserType(), $actor->getSupportUserId());
    }

    private function ownTicket(int $ticketId): Ticket
    {
        return $this->ownTickets()->findOrFail($ticketId);
    }
}
