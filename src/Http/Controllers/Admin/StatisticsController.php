<?php

declare(strict_types=1);

namespace Fillindev\Support\Http\Controllers\Admin;

use Fillindev\Support\Http\Controllers\SupportController;
use Fillindev\Support\Http\Requests\StatisticsRequest;
use Fillindev\Support\Http\TicketPresenter;
use Fillindev\Support\Models\Ticket;
use Fillindev\Support\Services\StatisticsService;
use Illuminate\Http\JsonResponse;
use OpenApi\Attributes as OA;

#[OA\Tag(
    name: 'Admin Support Statistics',
    description: 'Сводка по тикетам. Доступ: super admin, роль support senior или право view support statistics.',
)]
class StatisticsController extends SupportController
{
    public function __construct(
        TicketPresenter $presenter,
        private readonly StatisticsService $statistics,
    ) {
        parent::__construct($presenter);
    }

    #[OA\Get(
        path: '/support/admin/statistics',
        tags: ['Admin Support Statistics'],
        summary: 'Статистика тикетов',
        description: 'Период по умолчанию — с начала текущего месяца по сегодня. Без operator_id сводка по всем обращениям и нагрузка операторов. С operator_id карточки и графики только по тикетам, где этот оператор назначен или участвует в чате, а operators равен null. total — созданные за период (на экране всех операторов «Всего обращений», на экране одного — «Получено»). solved — статус resolved или closed («Решено» и числитель «Доли решённых»). closed — только closed. first_response_minutes и resolution_hours — средние, null если таких тикетов нет.',
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'date_from', in: 'query', required: false, schema: new OA\Schema(type: 'string', format: 'date', example: '2026-11-01')),
            new OA\Parameter(name: 'date_to', in: 'query', required: false, schema: new OA\Schema(type: 'string', format: 'date', example: '2026-11-30')),
            new OA\Parameter(name: 'operator_id', in: 'query', required: false, description: 'Id оператора. Пусто — все операторы.', schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'operator_type', in: 'query', required: false, description: 'Тип модели оператора. По умолчанию user.', schema: new OA\Schema(type: 'string', example: 'user')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'OK',
                content: new OA\JsonContent(
                    required: ['data'],
                    properties: [
                        new OA\Property(
                            property: 'data',
                            type: 'object',
                            required: ['period', 'operator', 'cards', 'volume', 'statuses', 'priorities', 'categories', 'first_response', 'resolution', 'operators'],
                            properties: [
                                new OA\Property(property: 'period', type: 'object', properties: [
                                    new OA\Property(property: 'from', type: 'string', format: 'date'),
                                    new OA\Property(property: 'to', type: 'string', format: 'date'),
                                ]),
                                new OA\Property(property: 'operator', type: 'object', nullable: true, properties: [
                                    new OA\Property(property: 'type', type: 'string'),
                                    new OA\Property(property: 'id', type: 'integer'),
                                    new OA\Property(property: 'name', type: 'string', nullable: true),
                                ]),
                                new OA\Property(property: 'cards', type: 'object', properties: [
                                    new OA\Property(property: 'total', type: 'integer'),
                                    new OA\Property(property: 'open', type: 'integer'),
                                    new OA\Property(property: 'in_progress', type: 'integer'),
                                    new OA\Property(property: 'waiting_customer', type: 'integer'),
                                    new OA\Property(property: 'resolved', type: 'integer'),
                                    new OA\Property(property: 'closed', type: 'integer'),
                                    new OA\Property(property: 'solved', type: 'integer'),
                                    new OA\Property(property: 'first_response_minutes', type: 'integer', nullable: true),
                                    new OA\Property(property: 'resolution_hours', type: 'number', nullable: true),
                                    new OA\Property(property: 'solved_share', type: 'integer', description: 'Проценты, 0–100'),
                                ]),
                                new OA\Property(property: 'volume', type: 'array', items: new OA\Items(type: 'object', properties: [
                                    new OA\Property(property: 'date', type: 'string', format: 'date'),
                                    new OA\Property(property: 'created', type: 'integer'),
                                    new OA\Property(property: 'solved', type: 'integer'),
                                ])),
                                new OA\Property(property: 'statuses', type: 'object'),
                                new OA\Property(property: 'priorities', type: 'object'),
                                new OA\Property(property: 'categories', type: 'array', items: new OA\Items(type: 'object', properties: [
                                    new OA\Property(property: 'id', type: 'integer', nullable: true),
                                    new OA\Property(property: 'name', type: 'string'),
                                    new OA\Property(property: 'count', type: 'integer'),
                                ])),
                                new OA\Property(property: 'first_response', type: 'array', items: new OA\Items(type: 'object', properties: [
                                    new OA\Property(property: 'date', type: 'string', format: 'date'),
                                    new OA\Property(property: 'minutes', type: 'integer', nullable: true),
                                ])),
                                new OA\Property(property: 'resolution', type: 'array', items: new OA\Items(type: 'object', properties: [
                                    new OA\Property(property: 'date', type: 'string', format: 'date'),
                                    new OA\Property(property: 'hours', type: 'number', nullable: true),
                                ])),
                                new OA\Property(property: 'operators', type: 'array', nullable: true, items: new OA\Items(type: 'object', properties: [
                                    new OA\Property(property: 'type', type: 'string'),
                                    new OA\Property(property: 'id', type: 'integer'),
                                    new OA\Property(property: 'name', type: 'string', nullable: true),
                                    new OA\Property(property: 'received', type: 'integer'),
                                    new OA\Property(property: 'solved', type: 'integer'),
                                ])),
                            ],
                        ),
                    ],
                ),
            ),
            new OA\Response(response: 401, description: 'Unauthorized'),
            new OA\Response(response: 403, description: 'Нет доступа к статистике'),
            new OA\Response(ref: '#/components/responses/SupportUnprocessable', response: 422),
        ],
    )]
    public function index(StatisticsRequest $request): JsonResponse
    {
        $this->authorize('statistics', Ticket::class);

        return $this->attempt(fn () => response()->json([
            'data' => $this->statistics->forPeriod($request->validated()),
        ]));
    }
}
