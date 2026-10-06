<?php

declare(strict_types=1);

namespace Fillindev\Support\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'TicketResource',
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 1),
        new OA\Property(property: 'number', type: 'string', example: 'SUP-2026-00001'),
        new OA\Property(property: 'tenant_id', type: 'integer', nullable: true, example: 10),
        new OA\Property(property: 'requester_id', type: 'integer', example: 7),
        new OA\Property(property: 'assignee_id', type: 'integer', nullable: true, example: 3),
        new OA\Property(property: 'category_id', type: 'integer', nullable: true, example: 2),
        new OA\Property(
            property: 'category',
            type: 'object',
            nullable: true,
            properties: [
                new OA\Property(property: 'id', type: 'integer', example: 2),
                new OA\Property(property: 'code', type: 'string', example: 'technical'),
                new OA\Property(property: 'name', type: 'string', example: 'Технический вопрос'),
            ],
        ),
        new OA\Property(property: 'subject', type: 'string', example: 'Не открывается модуль'),
        new OA\Property(property: 'body', description: 'Текст первого публичного сообщения. В списке messages нет, тело берётся отсюда.', type: 'string', nullable: true, example: 'Текст первого сообщения'),
        new OA\Property(property: 'status', type: 'string', enum: ['open', 'in_progress', 'waiting_customer', 'resolved', 'closed'], example: 'open'),
        new OA\Property(property: 'priority', type: 'string', enum: ['low', 'normal', 'high', 'urgent'], example: 'normal'),
        new OA\Property(property: 'last_replied_at', type: 'string', format: 'date-time', nullable: true),
        new OA\Property(property: 'resolved_at', type: 'string', format: 'date-time', nullable: true),
        new OA\Property(property: 'closed_at', type: 'string', format: 'date-time', nullable: true),
        new OA\Property(property: 'requester_last_read_at', type: 'string', format: 'date-time', nullable: true, description: 'Время отправки последнего сообщения, прочитанного клиентом'),
        new OA\Property(property: 'agent_last_read_at', type: 'string', format: 'date-time', nullable: true, description: 'Время отправки последнего сообщения, прочитанного оператором'),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time', nullable: true),
        new OA\Property(property: 'updated_at', type: 'string', format: 'date-time', nullable: true),
        new OA\Property(property: 'messages', description: 'Есть только в карточке. Клиенту не отдаются внутренние заметки. В списке ключ отсутствует.', type: 'array', items: new OA\Items(ref: '#/components/schemas/TicketMessageResource')),
    ],
)]
class TicketResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'number' => $this->number,
            'tenant_id' => $this->tenant_id,
            'requester_id' => $this->requester_id,
            'assignee_id' => $this->assignee_id,
            'category_id' => $this->category_id,
            'category' => $this->whenLoaded('category', fn () => [
                'id' => $this->category->id,
                'code' => $this->category->code,
                'name' => $this->category->name,
            ]),
            'subject' => $this->subject,
            'body' => $this->resource->getAttribute('body'),
            'status' => $this->status?->value,
            'priority' => $this->priority?->value,
            'last_replied_at' => $this->last_replied_at,
            'resolved_at' => $this->resolved_at,
            'closed_at' => $this->closed_at,
            'requester_last_read_at' => $this->requester_last_read_at,
            'agent_last_read_at' => $this->agent_last_read_at,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'messages' => $this->when(
                $this->resource->relationLoaded('messages'),
                fn () => TicketMessageResource::collection($this->messages),
            ),
        ];
    }
}
