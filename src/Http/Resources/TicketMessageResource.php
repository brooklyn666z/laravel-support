<?php

declare(strict_types=1);

namespace Fillindev\Support\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'TicketMessageResource',
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 1),
        new OA\Property(property: 'ticket_id', type: 'integer', example: 1),
        new OA\Property(property: 'author_id', type: 'integer', example: 7),
        new OA\Property(
            property: 'author',
            type: 'object',
            nullable: true,
            properties: [
                new OA\Property(property: 'id', type: 'integer', example: 7),
                new OA\Property(property: 'name', type: 'string', example: 'Иван Иванов'),
                new OA\Property(property: 'email', type: 'string', example: 'ivan@example.com'),
                new OA\Property(property: 'phone_code', type: 'string', nullable: true, example: '+7'),
                new OA\Property(property: 'phone', type: 'string', nullable: true, example: '9001234567'),
            ],
        ),
        new OA\Property(property: 'is_internal', description: 'Внутренняя заметка оператора. В клиентской карточке таких сообщений нет.', type: 'boolean', example: false),
        new OA\Property(property: 'body', type: 'string', example: 'Текст сообщения'),
        new OA\Property(property: 'attachments', description: 'Вложения сообщения. Пустой массив, если файлов нет.', type: 'array', items: new OA\Items(
            properties: [
                new OA\Property(property: 'id', type: 'integer', example: 15),
                new OA\Property(property: 'file_name', type: 'string', example: 'note.txt'),
                new OA\Property(property: 'mime_type', type: 'string', example: 'text/plain'),
                new OA\Property(property: 'size', description: 'Размер в байтах', type: 'integer', example: 12288),
                new OA\Property(property: 'url', type: 'string', example: '/storage/note.txt'),
            ],
            type: 'object',
        )),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time', nullable: true),
    ],
)]
class TicketMessageResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $attachments = $this->resource->getAttribute('attachment_payload');

        return [
            'id' => $this->id,
            'ticket_id' => $this->ticket_id,
            'author_id' => $this->author_id,
            'author' => $this->resource->getAttribute('author_payload'),
            'is_internal' => $this->is_internal,
            'body' => $this->body,
            'attachments' => is_array($attachments) ? array_values($attachments) : [],
            'created_at' => $this->created_at,
        ];
    }
}
