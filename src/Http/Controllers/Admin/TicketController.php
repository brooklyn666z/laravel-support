<?php

declare(strict_types=1);

namespace Fillindev\Support\Http\Controllers\Admin;

use Fillindev\Support\Contracts\SupportAttachmentStore;
use Fillindev\Support\Contracts\SupportUserResolver;
use Fillindev\Support\Enums\TicketPriority;
use Fillindev\Support\Enums\TicketStatus;
use Fillindev\Support\Http\Controllers\SupportController;
use Fillindev\Support\Http\Requests\AssignTicketRequest;
use Fillindev\Support\Http\Requests\ChangePriorityRequest;
use Fillindev\Support\Http\Requests\ChangeStatusRequest;
use Fillindev\Support\Http\Requests\StoreMessageRequest;
use Fillindev\Support\Http\Resources\TicketResource;
use Fillindev\Support\Http\TicketPresenter;
use Fillindev\Support\Models\Ticket;
use Fillindev\Support\Services\MessageService;
use Fillindev\Support\Services\TicketService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;
use OpenApi\Attributes as OA;

#[OA\Tag(
    name: 'Admin Support Tickets',
    description: 'Очередь техподдержки платформы. Доступ: super admin и support agent.',
)]
class TicketController extends SupportController
{
    public function __construct(
        TicketPresenter $presenter,
        private readonly TicketService $tickets,
        private readonly MessageService $messages,
        private readonly SupportUserResolver $users,
        private readonly SupportAttachmentStore $attachments,
    ) {
        parent::__construct($presenter);
    }

    #[OA\Get(
        path: '/support/admin/tickets',
        tags: ['Admin Support Tickets'],
        summary: 'Очередь обращений',
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'status', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['open', 'in_progress', 'waiting_customer', 'resolved', 'closed'])),
            new OA\Parameter(name: 'tenant_id', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
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
    public function index(Request $request): JsonResponse
    {
        $this->authorize('handle', Ticket::class);

        $base = Ticket::query()
            ->when($request->query('tenant_id'), fn ($query, $tenantId) => $query->forTenant($tenantId));

        $page = (clone $base)
            ->with('category')
            ->when($request->query('status'), fn ($query, $status) => $query->where('status', $status))
            ->latest('id')
            ->paginate(15);
        $this->presenter->applyOpeningBodies($page->items());

        return TicketResource::collection($page)->additional([
            'summary' => $this->presenter->statusSummary($base),
        ])->response();
    }

    #[OA\Get(
        path: '/support/admin/tickets/{ticket}',
        tags: ['Admin Support Tickets'],
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
            new OA\Response(response: 404, description: 'Не найдено'),
        ],
    )]
    public function show(Ticket $ticket): JsonResponse
    {
        $this->authorize('handle', Ticket::class);
        $ticket = $this->tickets->markRead($ticket, true);

        return $this->ticketResponse($ticket, false);
    }

    #[OA\Post(
        path: '/support/admin/tickets/{ticket}/messages',
        tags: ['Admin Support Tickets'],
        summary: 'Ответ оператора или внутренняя заметка',
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'ticket', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['body'],
                properties: [
                    new OA\Property(property: 'body', type: 'string', example: 'Ответ клиенту'),
                    new OA\Property(property: 'is_internal', type: 'boolean', example: false),
                ],
            ),
        ),
        responses: [
            new OA\Response(response: 201, description: 'Created', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'data', ref: '#/components/schemas/TicketResource'),
            ])),
            new OA\Response(response: 422, description: 'Пустое сообщение или тикет закрыт'),
        ],
    )]
    public function storeMessage(StoreMessageRequest $request, Ticket $ticket): JsonResponse
    {
        $this->authorize('handle', Ticket::class);

        return $this->attempt(function () use ($request, $ticket) {
            $message = $this->messages->post(
                $ticket,
                $this->actor(),
                $request->validated('body'),
                $request->boolean('is_internal'),
            );
            $this->attachments->store($message, $this->uploadedFiles($request));

            return $this->ticketResponse($ticket, false, 201);
        });
    }

    #[OA\Post(
        path: '/support/admin/tickets/{ticket}/assign',
        tags: ['Admin Support Tickets'],
        summary: 'Назначить оператора',
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'ticket', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['assignee_id'],
                properties: [
                    new OA\Property(property: 'assignee_id', type: 'integer', example: 3),
                ],
            ),
        ),
        responses: [
            new OA\Response(response: 200, description: 'OK', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'data', ref: '#/components/schemas/TicketResource'),
            ])),
            new OA\Response(response: 422, description: 'Пользователь не найден'),
        ],
    )]
    public function assign(AssignTicketRequest $request, Ticket $ticket): JsonResponse
    {
        $this->authorize('handle', Ticket::class);

        return $this->attempt(function () use ($request, $ticket) {
            $assignee = $this->users->resolve($request->integer('assignee_id'));

            if ($assignee === null) {
                throw new InvalidArgumentException('Пользователь не найден.');
            }

            $ticket = $this->tickets->assign($ticket, $assignee, $this->actor());

            return $this->ticketResponse($ticket, false);
        });
    }

    #[OA\Post(
        path: '/support/admin/tickets/{ticket}/status',
        tags: ['Admin Support Tickets'],
        summary: 'Сменить статус',
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'ticket', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['status'],
                properties: [
                    new OA\Property(property: 'status', type: 'string', enum: ['open', 'in_progress', 'waiting_customer', 'resolved', 'closed']),
                ],
            ),
        ),
        responses: [
            new OA\Response(response: 200, description: 'OK', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'data', ref: '#/components/schemas/TicketResource'),
            ])),
            new OA\Response(response: 422, description: 'Недопустимый переход'),
        ],
    )]
    public function changeStatus(ChangeStatusRequest $request, Ticket $ticket): JsonResponse
    {
        $this->authorize('handle', Ticket::class);

        return $this->attempt(function () use ($request, $ticket) {
            $ticket = $this->tickets->changeStatus(
                $ticket,
                TicketStatus::from($request->validated('status')),
                $this->actor(),
            );

            return $this->ticketResponse($ticket, false);
        });
    }

    #[OA\Post(
        path: '/support/admin/tickets/{ticket}/priority',
        tags: ['Admin Support Tickets'],
        summary: 'Сменить приоритет',
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'ticket', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['priority'],
                properties: [
                    new OA\Property(property: 'priority', type: 'string', enum: ['low', 'normal', 'high', 'urgent']),
                ],
            ),
        ),
        responses: [
            new OA\Response(response: 200, description: 'OK', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'data', ref: '#/components/schemas/TicketResource'),
            ])),
            new OA\Response(response: 422, description: 'Ошибка валидации'),
        ],
    )]
    public function changePriority(ChangePriorityRequest $request, Ticket $ticket): JsonResponse
    {
        $this->authorize('handle', Ticket::class);

        return $this->attempt(function () use ($request, $ticket) {
            $ticket = $this->tickets->changePriority(
                $ticket,
                TicketPriority::from($request->validated('priority')),
                $this->actor(),
            );

            return $this->ticketResponse($ticket, false);
        });
    }

    #[OA\Post(
        path: '/support/admin/tickets/{ticket}/close',
        tags: ['Admin Support Tickets'],
        summary: 'Закрыть обращение',
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'ticket', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'OK', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'data', ref: '#/components/schemas/TicketResource'),
            ])),
            new OA\Response(response: 422, description: 'Недопустимый переход'),
        ],
    )]
    public function close(Ticket $ticket): JsonResponse
    {
        $this->authorize('handle', Ticket::class);

        return $this->attempt(function () use ($ticket) {
            $ticket = $this->tickets->close($ticket, $this->actor());

            return $this->ticketResponse($ticket, false);
        });
    }

    #[OA\Post(
        path: '/support/admin/tickets/{ticket}/reopen',
        tags: ['Admin Support Tickets'],
        summary: 'Переоткрыть обращение',
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'ticket', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'OK', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'data', ref: '#/components/schemas/TicketResource'),
            ])),
            new OA\Response(response: 422, description: 'Переоткрыть можно только из resolved или closed'),
        ],
    )]
    public function reopen(Ticket $ticket): JsonResponse
    {
        $this->authorize('handle', Ticket::class);

        return $this->attempt(function () use ($ticket) {
            $ticket = $this->tickets->reopen($ticket, $this->actor());

            return $this->ticketResponse($ticket, false);
        });
    }
}
