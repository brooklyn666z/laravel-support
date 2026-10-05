<?php

declare(strict_types=1);

namespace Fillindev\Support\Tests;

use Fillindev\Support\Attachments\NullAttachmentStore;
use Fillindev\Support\Contracts\SupportAttachmentStore;
use Fillindev\Support\Routing\SupportRoutes;
use Illuminate\Support\Facades\Route;

class SupportServiceProviderTest extends TestCase
{
    public function test_config_is_merged(): void
    {
        $this->assertSame('support_', config('support.table_prefix'));
        $this->assertSame('tickets', config('support.tables.tickets'));
        $this->assertNull(config('support.bindings.user_resolver'));
        $this->assertNull(config('support.bindings.attachment_store'));
        $this->assertInstanceOf(NullAttachmentStore::class, $this->app->make(SupportAttachmentStore::class));
    }

    public function test_provider_does_not_register_http_routes(): void
    {
        $uris = collect(Route::getRoutes())->map(fn ($route) => $route->uri());

        $this->assertFalse($uris->contains(fn ($uri) => str_contains((string) $uri, 'support/')));
    }

    public function test_registered_paths_do_not_include_account(): void
    {
        SupportRoutes::register();

        $uris = collect(Route::getRoutes())
            ->map(fn ($route) => (string) $route->uri())
            ->filter(fn (string $uri) => str_contains($uri, 'support'))
            ->unique()
            ->values()
            ->all();

        $expected = [
            'support/categories',
            'support/tickets',
            'support/tickets/{ticket}',
            'support/tickets/{ticket}/messages',
            'support/tickets/{ticket}/close',
            'support/admin/tickets',
            'support/admin/tickets/{ticket}',
            'support/admin/tickets/{ticket}/messages',
            'support/admin/tickets/{ticket}/assign',
            'support/admin/tickets/{ticket}/status',
            'support/admin/tickets/{ticket}/priority',
            'support/admin/tickets/{ticket}/close',
            'support/admin/tickets/{ticket}/reopen',
        ];

        foreach ($expected as $uri) {
            $this->assertContains($uri, $uris, $uri);
        }

        foreach ($uris as $uri) {
            $this->assertStringNotContainsString('account', $uri);
        }

        $this->assertNotContains('support/tickets/{ticket}/priority', $uris);
    }
}
