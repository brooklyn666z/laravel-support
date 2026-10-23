<?php

declare(strict_types=1);

namespace Fillindev\Support\Broadcasting;

use Fillindev\Support\Contracts\SupportableUser;
use Fillindev\Support\Models\Ticket;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Gate;

/**
 * Private-каналы техподдержки. Хост задаёт политику Ticket, пакет не знает роли.
 */
final class SupportChannelAuthorization
{
    public static function register(): void
    {
        Broadcast::channel(SupportChannels::QUEUE, function ($user): bool {
            return Gate::forUser($user)->allows('handle', Ticket::class);
        });

        Broadcast::channel(SupportChannels::LIFECYCLE, function ($user): bool {
            return Gate::forUser($user)->allows('handle', Ticket::class);
        });

        Broadcast::channel(SupportChannels::USER_PATTERN, function ($user, string $type, string $id): bool {
            return $user instanceof SupportableUser
                && $user->getSupportUserType() === $type
                && (string) $user->getSupportUserId() === $id;
        });

        Broadcast::channel(SupportChannels::TICKET_PATTERN, function ($user, string $id): bool {
            $ticket = Ticket::query()->find($id);

            if ($ticket === null) {
                return false;
            }

            return Gate::forUser($user)->allows('view', $ticket)
                || Gate::forUser($user)->allows('handle', Ticket::class);
        });

        Broadcast::channel(SupportChannels::TICKET_STAFF_PATTERN, function ($user, string $id): bool {
            if (! Ticket::query()->whereKey($id)->exists()) {
                return false;
            }

            return Gate::forUser($user)->allows('handle', Ticket::class);
        });
    }
}
