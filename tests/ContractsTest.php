<?php

declare(strict_types=1);

namespace Fillindev\Support\Tests;

use Fillindev\Support\Contracts\SupportNotificationGateway;
use Fillindev\Support\Contracts\SupportTenantResolver;
use Fillindev\Support\Contracts\SupportUserResolver;
use Illuminate\Contracts\Container\BindingResolutionException;

class ContractsTest extends TestCase
{
    public function test_host_contracts_fail_clearly_until_configured(): void
    {
        $this->expectException(BindingResolutionException::class);
        $this->expectExceptionMessage('support.bindings.user_resolver');

        $this->app->make(SupportUserResolver::class);
    }

    public function test_tenant_resolver_and_gateway_are_bound(): void
    {
        $this->assertTrue($this->app->bound(SupportTenantResolver::class));
        $this->assertTrue($this->app->bound(SupportNotificationGateway::class));
    }
}
