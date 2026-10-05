<?php

declare(strict_types=1);

namespace Fillindev\Support\Tests\Stubs;

use Fillindev\Support\Contracts\SupportAttachmentStore;
use Fillindev\Support\Models\TicketMessage;

final class FakeAttachmentStore implements SupportAttachmentStore
{
    /**
     * @var array<int, list<array{id: int, file_name: string, mime_type: string, size: int, url: string}>>
     */
    public static array $byMessage = [];

    public static function reset(): void
    {
        self::$byMessage = [];
    }

    public function store(TicketMessage $message, array $files): void
    {
        foreach (array_values($files) as $index => $file) {
            self::$byMessage[$message->id][] = [
                'id' => $index + 1,
                'file_name' => $file->getClientOriginalName(),
                'mime_type' => $file->getClientMimeType() ?: 'application/octet-stream',
                'size' => (int) $file->getSize(),
                'url' => '/storage/'.$file->getClientOriginalName(),
            ];
        }
    }

    public function list(TicketMessage $message): array
    {
        return self::$byMessage[$message->id] ?? [];
    }
}
