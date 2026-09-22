<?php

declare(strict_types=1);

namespace Fillindev\Support\Tests;

class SupportServiceProviderTest extends TestCase
{
    public function test_config_is_merged(): void
    {
        $this->assertSame('support_', config('support.table_prefix'));
        $this->assertSame('tickets', config('support.tables.tickets'));
        $this->assertNull(config('support.bindings.user_resolver'));
    }
}
