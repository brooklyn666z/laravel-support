<?php

declare(strict_types=1);

namespace Fillindev\Support\Contracts;

/**
 * Контекст арендатора тикета.
 * Хост реализует контракт на своей модели тенанта (в Fillindev — Account).
 */
interface SupportTenant
{
    public function getSupportTenantId(): int|string;

    public function getSupportTenantLabel(): string;
}
