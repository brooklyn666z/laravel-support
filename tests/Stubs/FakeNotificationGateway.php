<?php

declare(strict_types=1);

namespace Fillindev\Support\Tests\Stubs;

use Fillindev\Support\Contracts\SupportableUser;
use Fillindev\Support\Contracts\SupportNotificationGateway;

final class FakeNotificationGateway implements SupportNotificationGateway
{
    /** @var list<array{event: string, recipients: list<SupportableUser>, context: array<string, mixed>}> */
    public static array $sent = [];

    public function notify(string $event, array $recipients, array $context = []): void
    {
        self::$sent[] = [
            'event' => $event,
            'recipients' => $recipients,
            'context' => $context,
        ];
    }
}
