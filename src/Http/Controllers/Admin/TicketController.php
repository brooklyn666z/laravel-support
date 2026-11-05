<?php

declare(strict_types=1);

namespace Fillindev\Support\Http\Controllers\Admin;

use Fillindev\Support\Broadcasting\SupportRealtime;
use Fillindev\Support\Contracts\SupportAttachmentStore;
use Fillindev\Support\Contracts\SupportUserResolver;
use Fillindev\Support\Enums\TicketPriority;
use Fillindev\Support\Enums\TicketStatus;
use Fillindev\Support\Http\AdminTicketQuery;
use Fillindev\Support\Http\Controllers\SupportController;
use Fillindev\Support\Http\Requests\AssignTicketRequest;
use Fillindev\Support\Http\Requests\IndexTicketsRequest;
use Fillindev\Support\Http\Requests\JoinOperatorRequest;
use Fillindev\Support\Http\Requests\ChangePriorityRequest;
use Fillindev\Support\Http\Requests\ChangeStatusRequest;
use Fillindev\Support\Http\Requests\StoreMessageRequest;
use Fillindev\Support\Http\Resources\TicketResource;
use Fillindev\Support\Http\TicketPresenter;
use Fillindev\Support\Models\Ticket;
use Fillindev\Support\Services\MessageService;
use Fillindev\Support\Services\TicketService;
use Illuminate\Http\JsonResponse;
use InvalidArgumentException;
use OpenApi\Attributes as OA;

#[OA\Tag(
    name: 'Admin Support Tickets',
    description: 'Очередь техподдержки. Доступ: super admin, роль support agent или право handle support. В карточке есть внутренние заметки.',
)]
class TicketController extends SupportController
{
    public function __construct(
        TicketPresenter $presenter,
        private readonly TicketService $tickets,
        private readonly MessageService $messages,
        private readonly SupportUserResolver $users,
        private readonly SupportAttachmentStore $attachments,
        private readonly SupportRealtime $realtime,
    ) {
        parent::__construct($presenter);
    }

    #[OA\Get(
        path: '/support/admin/tickets',
        tags: ['Admin Support Tickets'],
        summary: 'Очередь обращений',
        description: 'По 15 записей, без messages. status фильтрует только список. id, category_id, subject, priority, assignee_id, updated_from, updated_to, requester_id и requester_type сужают и список, и summary.',
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'query', required: false, description: 'Точный id. Влияет и на список, и на summary.', schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'status', in: 'query', required: false, description: 'Фильтр списка. На summary не влияет.', schema: new OA\Schema(type: 'string', enum: ['open', 'in_progress', 'waiting_customer', 'resolved', 'closed'])),
            new OA\Parameter(name: 'priority', in: 'query', required: false, description: 'Приоритет. Влияет и на список, и на summary.', schema: new OA\Schema(type: 'string', enum: ['low', 'normal', 'high', 'urgent'])),
            new OA\Parameter(name: 'category_id', in: 'query', required: false, description: 'Категория. Влияет и на список, и на summary.', schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'subject', in: 'query', required: false, description: 'Часть темы. Влияет и на список, и на summary.', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'assignee_id', in: 'query', required: false, description: 'Id специалиста. Влияет и на список, и на summary.', schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'assignee_type', in: 'query', required: false, description: 'Тип модели специалиста. Имеет смысл вместе с assignee_id.', schema: new OA\Schema(type: 'string', example: 'user')),
            new OA\Parameter(name: 'updated_from', in: 'query', required: false, description: 'Начало периода обновления, включительно. Влияет и на список, и на summary.', schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\Parameter(name: 'updated_to', in: 'query', required: false, description: 'Конец периода обновления, включительно. Влияет и на список, и на summary.', schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\Parameter(name: 'requester_type', in: 'query', required: false, description: 'Тип автора: user или agent. Влияет и на список, и на summary.', schema: new OA\Schema(type: 'string', example: 'agent')),
            new OA\Parameter(name: 'requester_id', in: 'query', required: false, description: 'Id автора. Влияет и на список, и на summary.', schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', minimum: 1)),
        ],
        responses: [
            new OA\Response(response: 200, description: 'OK', content: new OA\JsonContent(ref: '#/components/schemas/TicketPage')),
            new OA\Response(response: 401, description: 'Unauthorized'),
            new OA\Response(response: 403, description: 'Нет доступа к очереди'),
        ],
    )]
    public function index(IndexTicketsRequest $request): JsonResponse
    {
        $this->authorize('handle', Ticket::class);

        $filters = $request->validated();
        $base = AdminTicketQuery::base($filters);
        $page = AdminTicketQuery::page($base, $filters)
            ->with(['category', 'operators'])
            ->latest('id')
            ->paginate(15);
        $this->presenter->applyOpeningBodies($page->items());
        $this->presenter->applyOperators($page->items());

        return TicketResource::collection($page)->additional([
            'summary' => $this->presenter->statusSummary($base),
        ])->response();
    }

    #[OA\Get(
        path: '/support/admin/tickets/{ticket}',
        tags: ['Admin Support Tickets'],
        summary: 'Карточка обращения',
        description: 'Отмечает обращение прочитанным оператором. В messages входят внутренние заметки.',
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'ticket', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'OK', content: new OA\JsonContent(ref: '#/components/schemas/TicketData')),
            new OA\Response(response: 401, description: 'Unauthorized'),
            new OA\Response(response: 403, description: 'Нет доступа к очереди'),
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
        description: 'В закрытое обращение писать нельзя. Публичный ответ добавляет автора в участники чата, внутренних заметок это не касается. Отвечать может любой оператор с доступом handle, не только назначенный специалист. Ответ — карточка, включая внутренние заметки.',
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'ticket', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: [
                new OA\MediaType(mediaType: 'application/json', schema: new OA\Schema(ref: '#/components/schemas/StoreAdminMessageRequest')),
                new OA\MediaType(mediaType: 'multipart/form-data', schema: new OA\Schema(ref: '#/components/schemas/StoreAdminMessageRequest')),
            ],
        ),
        responses: [
            new OA\Response(response: 201, description: 'Created', content: new OA\JsonContent(ref: '#/components/schemas/TicketData')),
            new OA\Response(response: 401, description: 'Unauthorized'),
            new OA\Response(response: 403, description: 'Нет доступа к очереди'),
            new OA\Response(response: 404, description: 'Не найдено'),
            new OA\Response(ref: '#/components/responses/SupportUnprocessable', response: 422),
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
            $this->realtime->message($ticket, $message);

            return $this->ticketResponse($ticket, false, 201);
        });
    }

    #[OA\Post(
        path: '/support/admin/tickets/{ticket}/assign',
        tags: ['Admin Support Tickets'],
        summary: 'Назначить специалиста',
        description: 'Ставит assignee и добавляет его в чат. Уже участвующие операторы остаются.',
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
                    new OA\Property(property: 'assignee_type', type: 'string', example: 'user', description: 'Тип модели исполнителя. По умолчанию user.'),
                ],
            ),
        ),
        responses: [
            new OA\Response(response: 200, description: 'OK', content: new OA\JsonContent(ref: '#/components/schemas/TicketData')),
            new OA\Response(response: 401, description: 'Unauthorized'),
            new OA\Response(response: 403, description: 'Нет доступа к очереди'),
            new OA\Response(response: 404, description: 'Не найдено'),
            new OA\Response(ref: '#/components/responses/SupportUnprocessable', response: 422),
        ],
    )]
    public function assign(AssignTicketRequest $request, Ticket $ticket): JsonResponse
    {
        $this->authorize('handle', Ticket::class);

        return $this->attempt(function () use ($request, $ticket) {
            $assignee = $this->users->resolve(
                (string) $request->input('assignee_type', 'user'),
                $request->integer('assignee_id'),
            );

            if ($assignee === null) {
                throw new InvalidArgumentException('Пользователь не найден.');
            }

            $ticket = $this->tickets->assign($ticket, $assignee, $this->actor());

            return $this->ticketResponse($ticket, false);
        });
    }

    #[OA\Post(
        path: '/support/admin/tickets/{ticket}/operators',
        tags: ['Admin Support Tickets'],
        summary: 'Добавить оператора в чат',
        description: 'Несколько операторов могут отвечать в одном обращении. Повтор для того же оператора ничего не меняет. Назначенный специалист (assignee) не сбрасывается.',
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'ticket', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['operator_id'],
                properties: [
                    new OA\Property(property: 'operator_id', type: 'integer', example: 4),
                    new OA\Property(property: 'operator_type', type: 'string', example: 'user', description: 'Тип модели оператора. По умолчанию user.'),
                ],
            ),
        ),
        responses: [
            new OA\Response(response: 200, description: 'OK', content: new OA\JsonContent(ref: '#/components/schemas/TicketData')),
            new OA\Response(response: 401, description: 'Unauthorized'),
            new OA\Response(response: 403, description: 'Нет доступа к очереди'),
            new OA\Response(response: 404, description: 'Не найдено'),
            new OA\Response(ref: '#/components/responses/SupportUnprocessable', response: 422),
        ],
    )]
    public function joinOperator(JoinOperatorRequest $request, Ticket $ticket): JsonResponse
    {
        $this->authorize('handle', Ticket::class);

        return $this->attempt(function () use ($request, $ticket) {
            $operator = $this->users->resolve(
                (string) $request->input('operator_type', 'user'),
                $request->integer('operator_id'),
            );

            if ($operator === null) {
                throw new InvalidArgumentException('Пользователь не найден.');
            }

            $ticket = $this->tickets->attachOperator($ticket, $operator, $this->actor());

            return $this->ticketResponse($ticket, false);
        });
    }

    #[OA\Post(
        path: '/support/admin/tickets/{ticket}/status',
        tags: ['Admin Support Tickets'],
        summary: 'Сменить статус',
        description: 'Допустимые переходы: open → in_progress, waiting_customer, resolved, closed; in_progress → waiting_customer, resolved, closed; waiting_customer → in_progress, resolved, closed; resolved → open, closed; closed → open.',
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
            new OA\Response(response: 200, description: 'OK', content: new OA\JsonContent(ref: '#/components/schemas/TicketData')),
            new OA\Response(response: 401, description: 'Unauthorized'),
            new OA\Response(response: 403, description: 'Нет доступа к очереди'),
            new OA\Response(response: 404, description: 'Не найдено'),
            new OA\Response(ref: '#/components/responses/SupportUnprocessable', response: 422),
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
            new OA\Response(response: 200, description: 'OK', content: new OA\JsonContent(ref: '#/components/schemas/TicketData')),
            new OA\Response(response: 401, description: 'Unauthorized'),
            new OA\Response(response: 403, description: 'Нет доступа к очереди'),
            new OA\Response(response: 404, description: 'Не найдено'),
            new OA\Response(ref: '#/components/responses/SupportUnprocessable', response: 422),
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
        description: 'Тело запроса не нужно. Если обращение уже закрыто, статус не меняется и ответ остаётся 200.',
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'ticket', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'OK', content: new OA\JsonContent(ref: '#/components/schemas/TicketData')),
            new OA\Response(response: 401, description: 'Unauthorized'),
            new OA\Response(response: 403, description: 'Нет доступа к очереди'),
            new OA\Response(response: 404, description: 'Не найдено'),
            new OA\Response(ref: '#/components/responses/SupportUnprocessable', response: 422),
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
        description: 'Тело запроса не нужно. Статус становится open. Допустимо только из resolved или closed.',
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'ticket', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'OK', content: new OA\JsonContent(ref: '#/components/schemas/TicketData')),
            new OA\Response(response: 401, description: 'Unauthorized'),
            new OA\Response(response: 403, description: 'Нет доступа к очереди'),
            new OA\Response(response: 404, description: 'Не найдено'),
            new OA\Response(ref: '#/components/responses/SupportUnprocessable', response: 422),
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
