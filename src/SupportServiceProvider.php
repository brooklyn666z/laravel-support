<?php

declare(strict_types=1);

namespace Fillindev\Support;

use Fillindev\Support\Contracts\SupportNotificationGateway;
use Fillindev\Support\Contracts\SupportTenantResolver;
use Fillindev\Support\Contracts\SupportUserResolver;
use Fillindev\Support\Listeners\RecordTicketHistory;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Support\ServiceProvider;

class SupportServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/support.php',
            'support'
        );

        $this->registerHostBindings();
    }

    private function registerHostBindings(): void
    {
        $map = [
            SupportUserResolver::class => 'support.bindings.user_resolver',
            SupportTenantResolver::class => 'support.bindings.tenant_resolver',
            SupportNotificationGateway::class => 'support.bindings.notification_gateway',
        ];

        foreach ($map as $abstract => $configKey) {
            $this->app->bind($abstract, function ($app) use ($abstract, $configKey) {
                $concrete = $app['config']->get($configKey);

                if (! is_string($concrete) || $concrete === '') {
                    throw new BindingResolutionException(
                        "{$abstract} is not configured. Set [{$configKey}] to a host implementation."
                    );
                }

                return $app->make($concrete);
            });
        }
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->app->make('events')->subscribe(RecordTicketHistory::class);

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/support.php' => config_path('support.php'),
            ], 'support-config');
        }
    }
}
