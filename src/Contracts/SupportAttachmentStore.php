<?php

declare(strict_types=1);

namespace Fillindev\Support\Contracts;

use Fillindev\Support\Models\TicketMessage;

/**
 * Хранение вложений сообщения.
 * Пакет не подключает media-библиотеку: хост сохраняет файлы и отдаёт ссылки.
 */
interface SupportAttachmentStore
{
    /**
     * @param  list<\Illuminate\Http\UploadedFile>  $files
     */
    public function store(TicketMessage $message, array $files): void;

    /**
     * @return list<array{id: int|string, file_name: string, mime_type: string|null, size: int, url: string}>
     */
    public function list(TicketMessage $message): array;
}
