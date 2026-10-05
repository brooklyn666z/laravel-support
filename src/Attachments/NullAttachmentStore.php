<?php

declare(strict_types=1);

namespace Fillindev\Support\Attachments;

use Fillindev\Support\Contracts\SupportAttachmentStore;
use Fillindev\Support\Models\TicketMessage;

/**
 * Заглушка, пока хост не указал свою реализацию.
 */
final class NullAttachmentStore implements SupportAttachmentStore
{
    public function store(TicketMessage $message, array $files): void {}

    public function list(TicketMessage $message): array
    {
        return [];
    }
}
