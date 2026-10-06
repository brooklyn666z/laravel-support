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
    ) {
        parent::__construct($presenter);
    }

    #[OA\Get(
        path: '/support/admin/tickets',
        tags: ['Admin Support Tickets'],
        summary: 'Очередь обращений',
        description: 'По 15 записей, без messages. status фильтрует список и не фильтрует summary. tenant_id сужает и список, и summary.',
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'status', in: 'query', required: false, description: 'Фильтр списка. На summary не влияет.', schema: new OA\Schema(type: 'string', enum: ['open', 'in_progress', 'waiting_customer', 'resolved', 'closed'])),
            new OA\Parameter(name: 'tenant_id', in: 'query', required: false, description: 'Аккаунт. Влияет и на список, и на summary.', schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', minimum: 1)),
        ],
        responses: [
            new OA\Response(response: 200, description: 'OK', content: new OA\JsonContent(ref: '#/components/schemas/TicketPage')),
            new OA\Response(response: 401, description: 'Unauthorized'),
            new OA\Response(response: 403, description: 'Нет доступа к очереди'),
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
        description: 'В закрытое обращение писать нельзя. Ответ — карточка, включая внутренние заметки.',
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
