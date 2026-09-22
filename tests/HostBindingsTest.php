<?php

declare(strict_types=1);

namespace Fillindev\Support\Tests;

use Fillindev\Support\Contracts\SupportNotificationGateway;
use Fillindev\Support\Contracts\SupportTenantResolver;
use Fillindev\Support\Contracts\SupportUserResolver;
use Fillindev\Support\Tests\Stubs\FakeNotificationGateway;
use Fillindev\Support\Tests\Stubs\FakeTenantResolver;
use Fillindev\Support\Tests\Stubs\FakeUser;
use Fillindev\Support\Tests\Stubs\FakeUserResolver;

class HostBindingsTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        $app['config']->set('support.bindings', [
            'user_resolver' => FakeUserResolver::class,
            'tenant_resolver' => FakeTenantResolver::class,
            'notification_gateway' => FakeNotificationGateway::class,
        ]);
    }

    public function test_resolvers_and_gateway_are_resolved_from_config(): void
    {
        $user = $this->app->make(SupportUserResolver::class)->resolve(7);
        $tenant = $this->app->make(SupportTenantResolver::class)->resolve(3);

        $this->assertInstanceOf(FakeUser::class, $user);
        $this->assertSame(7, $user->getSupportUserId());
        $this->assertSame('Иван', $user->getSupportDisplayName());
        $this->assertSame(3, $tenant?->getSupportTenantId());
        $this->assertSame('Ромашка', $tenant?->getSupportTenantLabel());
        $this->assertNull($this->app->make(SupportTenantResolver::class)->resolve(null));

        FakeNotificationGateway::$sent = [];
        $this->app->make(SupportNotificationGateway::class)->notify('ticket.created', [$user], [
            'ticket_id' => 1,
        ]);

        $this->assertSame('ticket.created', FakeNotificationGateway::$sent[0]['event']);
        $this->assertSame([$user], FakeNotificationGateway::$sent[0]['recipients']);
        $this->assertSame(['ticket_id' => 1], FakeNotificationGateway::$sent[0]['context']);
    }
}
