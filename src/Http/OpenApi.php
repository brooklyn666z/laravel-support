<?php

declare(strict_types=1);

namespace Fillindev\Support\Http;

use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'TicketStatusSummary',
    description: 'Число обращений по статусам. Считается по той же выборке, что и список, до фильтра status и до пагинации.',
    required: ['open', 'in_progress', 'waiting_customer', 'resolved', 'closed'],
    properties: [
        new OA\Property(property: 'open', type: 'integer', example: 1),
        new OA\Property(property: 'in_progress', type: 'integer', example: 0),
        new OA\Property(property: 'waiting_customer', type: 'integer', example: 0),
        new OA\Property(property: 'resolved', type: 'integer', example: 0),
        new OA\Property(property: 'closed', type: 'integer', example: 1),
    ],
)]
#[OA\Schema(
    schema: 'SupportPaginationLinks',
    properties: [
        new OA\Property(property: 'first', type: 'string', nullable: true),
        new OA\Property(property: 'last', type: 'string', nullable: true),
        new OA\Property(property: 'prev', type: 'string', nullable: true),
        new OA\Property(property: 'next', type: 'string', nullable: true),
    ],
)]
#[OA\Schema(
    schema: 'SupportPaginationMeta',
    properties: [
        new OA\Property(property: 'current_page', type: 'integer', example: 1),
        new OA\Property(property: 'from', type: 'integer', nullable: true, example: 1),
        new OA\Property(property: 'last_page', type: 'integer', example: 1),
        new OA\Property(property: 'path', type: 'string'),
        new OA\Property(property: 'per_page', type: 'integer', example: 15),
        new OA\Property(property: 'to', type: 'integer', nullable: true, example: 15),
        new OA\Property(property: 'total', type: 'integer', example: 1),
    ],
)]
#[OA\Schema(
    schema: 'TicketPage',
    description: 'Страница обращений. В элементах data нет ключа messages: текст первого публичного сообщения лежит в body.',
    required: ['data', 'links', 'meta', 'summary'],
    properties: [
        new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/TicketResource')),
        new OA\Property(property: 'links', ref: '#/components/schemas/SupportPaginationLinks'),
        new OA\Property(property: 'meta', ref: '#/components/schemas/SupportPaginationMeta'),
        new OA\Property(property: 'summary', ref: '#/components/schemas/TicketStatusSummary'),
    ],
)]
#[OA\Schema(
    schema: 'TicketData',
    required: ['data'],
    properties: [
        new OA\Property(property: 'data', ref: '#/components/schemas/TicketResource'),
    ],
)]
#[OA\Schema(
    schema: 'SupportCategory',
    required: ['id', 'code', 'name'],
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 2),
        new OA\Property(property: 'code', type: 'string', example: 'technical'),
        new OA\Property(property: 'name', type: 'string', example: 'Технический вопрос'),
    ],
)]
#[OA\Schema(
    schema: 'StoreTicketRequest',
    required: ['subject', 'body'],
    properties: [
        new OA\Property(property: 'subject', type: 'string', maxLength: 255, example: 'Не открывается модуль'),
        new OA\Property(property: 'body', type: 'string', example: 'Текст обращения'),
        new OA\Property(property: 'category_id', type: 'integer', nullable: true, example: 2),
        new OA\Property(property: 'priority', type: 'string', nullable: true, enum: ['low', 'normal', 'high', 'urgent'], example: 'normal'),
        new OA\Property(
            property: 'attachments',
            description: 'До 10 файлов, каждый не больше 10 МБ. Только multipart/form-data, имя поля attachments[].',
            type: 'array',
            items: new OA\Items(type: 'string', format: 'binary'),
            maxItems: 10,
        ),
    ],
)]
#[OA\Schema(
    schema: 'StoreClientMessageRequest',
    required: ['body'],
    properties: [
        new OA\Property(property: 'body', type: 'string', example: 'Уточнение'),
        new OA\Property(
            property: 'attachments',
            description: 'До 10 файлов, каждый не больше 10 МБ. Только multipart/form-data, имя поля attachments[]. Клиентская заметка всегда публичная.',
            type: 'array',
            items: new OA\Items(type: 'string', format: 'binary'),
            maxItems: 10,
        ),
    ],
)]
#[OA\Schema(
    schema: 'StoreAdminMessageRequest',
    required: ['body'],
    properties: [
        new OA\Property(property: 'body', type: 'string', example: 'Ответ клиенту'),
        new OA\Property(
            property: 'is_internal',
            description: 'Внутренняя заметка. Клиент её не видит, и она не двигает last_replied_at. В multipart передаётся как 1/0 или true/false.',
            type: 'boolean',
            example: false,
        ),
        new OA\Property(
            property: 'attachments',
            description: 'До 10 файлов, каждый не больше 10 МБ. Только multipart/form-data, имя поля attachments[].',
            type: 'array',
            items: new OA\Items(type: 'string', format: 'binary'),
            maxItems: 10,
        ),
    ],
)]
#[OA\Response(
    response: 'SupportUnauthorized',
    description: 'Unauthorized',
)]
#[OA\Response(
    response: 'SupportForbidden',
    description: 'Forbidden',
)]
#[OA\Response(
    response: 'SupportNotFound',
    description: 'Не найдено',
)]
#[OA\Response(
    response: 'SupportUnprocessable',
    description: 'Ошибка валидации (message и errors по полям) либо недопустимое действие (errors — массив строк).',
    content: new OA\JsonContent(
        examples: [
            new OA\Examples(
                example: 'validation',
                summary: 'Валидация',
                value: [
                    'message' => 'The subject field is required.',
                    'errors' => ['subject' => ['The subject field is required.']],
                ],
            ),
            new OA\Examples(
                example: 'closed',
                summary: 'Тикет закрыт',
                value: ['errors' => ['Закрытое обращение нельзя дополнить сообщением. Сначала переоткройте его.']],
            ),
            new OA\Examples(
                example: 'transition',
                summary: 'Недопустимый переход',
                value: ['errors' => ['Нельзя сменить статус с closed на in_progress.']],
            ),
        ],
    ),
)]
final class OpenApi
{
}
