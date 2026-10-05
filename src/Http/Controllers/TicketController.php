<?php

declare(strict_types=1);

namespace Fillindev\Support\Http\Controllers;

use Fillindev\Support\Contracts\SupportAttachmentStore;
use Fillindev\Support\Contracts\SupportTenantResolver;
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
use OpenApi\Attributes as OA;

#[OA\Tag(
    name: 'Support Tickets',
    description: 'Обращения клиента в техподдержку платформы.',
)]
class TicketController extends SupportController
{
    public function __construct(
        TicketPresenter $presenter,
        private readonly TicketService $tickets,
        private readonly MessageService $messages,
        private readonly SupportTenantResolver $tenants,
        private readonly SupportAttachmentStore $attachments,
    ) {
        parent::__construct($presenter);
    }

    #[OA\Get(
        path: '/support/categories',
        tags: ['Support Tickets'],
        summary: 'Категории обращений',
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: 'OK',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'data', type: 'array', items: new OA\Items(
                            properties: [
                                new OA\Property(property: 'id', type: 'integer', example: 2),
                                new OA\Property(property: 'code', type: 'string', example: 'technical'),
                                new OA\Property(property: 'name', type: 'string', example: 'Технический вопрос'),
                            ],
                            type: 'object',
                        )),
                    ],
                ),
            ),
            new OA\Response(response: 401, description: 'Unauthorized'),
            new OA\Response(response: 403, description: 'Forbidden'),
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
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'page', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'По 15 записей', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/TicketResource')),
                new OA\Property(property: 'summary', type: 'object'),
            ])),
            new OA\Response(response: 401, description: 'Unauthorized'),
            new OA\Response(response: 403, description: 'Forbidden'),
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

    #[OA\Post(
        path: '/support/tickets',
        tags: ['Support Tickets'],
        summary: 'Создать обращение',
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['subject', 'body'],
                properties: [
                    new OA\Property(property: 'subject', type: 'string', example: 'Не открывается модуль'),
                    new OA\Property(property: 'body', type: 'string', example: 'Текст обращения'),
                    new OA\Property(property: 'category_id', type: 'integer', nullable: true, example: 2),
                    new OA\Property(property: 'priority', type: 'string', nullable: true, enum: ['low', 'normal', 'high', 'urgent']),
                ],
            ),
        ),
        responses: [
            new OA\Response(response: 201, description: 'Created', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'data', ref: '#/components/schemas/TicketResource'),
            ])),
            new OA\Response(response: 401, description: 'Unauthorized'),
            new OA\Response(response: 403, description: 'Forbidden'),
            new OA\Response(response: 422, description: 'Ошибка валидации'),
        ],
    )]
    public function store(StoreTicketRequest $request): JsonResponse
    {
        $this->authorize('create', Ticket::class);

        return $this->attempt(function () use ($request) {
            $ticket = $this->tickets->create(
                requester: $this->actor(),
                subject: $request->validated('subject'),
                body: $request->validated('body'),
                tenant: $this->tenants->current(),
                categoryId: $request->validated('category_id'),
                priority: $request->enum('priority', TicketPriority::class) ?? TicketPriority::Normal,
            );

            $opening = $ticket->messages()->oldest('id')->first();
            if ($opening) {
                $this->attachments->store($opening, $this->uploadedFiles($request));
            }

            return $this->ticketResponse($ticket, true, 201);
        });
    }

    #[OA\Get(
        path: '/support/tickets/{ticket}',
        tags: ['Support Tickets'],
        summary: 'Карточка обращения',
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'ticket', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'OK', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'data', ref: '#/components/schemas/TicketResource'),
            ])),
            new OA\Response(response: 401, description: 'Unauthorized'),
            new OA\Response(response: 403, description: 'Forbidden'),
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
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'ticket', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['body'],
                properties: [
                    new OA\Property(property: 'body', type: 'string', example: 'Уточнение'),
                ],
            ),
        ),
        responses: [
            new OA\Response(response: 201, description: 'Created', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'data', ref: '#/components/schemas/TicketResource'),
            ])),
            new OA\Response(response: 401, description: 'Unauthorized'),
            new OA\Response(response: 422, description: 'Пустое сообщение или тикет закрыт'),
        ],
    )]
    public function storeMessage(StoreMessageRequest $request, int $ticket): JsonResponse
    {
        $ticket = $this->ownTicket($ticket);
        $this->authorize('reply', $ticket);

        return $this->attempt(function () use ($request, $ticket) {
            $message = $this->messages->post($ticket, $this->actor(), $request->validated('body'), false);
            $this->attachments->store($message, $this->uploadedFiles($request));

            return $this->ticketResponse($ticket, true, 201);
        });
    }

    #[OA\Post(
        path: '/support/tickets/{ticket}/close',
        tags: ['Support Tickets'],
        summary: 'Закрыть своё обращение',
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'ticket', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'OK', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'data', ref: '#/components/schemas/TicketResource'),
            ])),
            new OA\Response(response: 401, description: 'Unauthorized'),
            new OA\Response(response: 422, description: 'Недопустимый переход статуса'),
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
        $tenant = $this->tenants->current();

        return Ticket::query()
            ->forTenant($tenant?->getSupportTenantId())
            ->forRequester($this->actor()->getSupportUserId());
    }

    private function ownTicket(int $ticketId): Ticket
    {
        return $this->ownTickets()->findOrFail($ticketId);
    }
}
