<?php

declare(strict_types=1);

namespace Fillindev\Support;

final class SupportTables
{
    public static function name(string $key): string
    {
        $defaults = [
            'tickets' => 'tickets',
            'ticket_messages' => 'ticket_messages',
            'ticket_categories' => 'ticket_categories',
            'ticket_events' => 'ticket_events',
        ];

        $prefix = (string) config('support.table_prefix', 'support_');
        $table = (string) config('support.tables.'.$key, $defaults[$key] ?? $key);

        return $prefix.$table;
    }
}
