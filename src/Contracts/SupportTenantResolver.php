<?php

declare(strict_types=1);

namespace Fillindev\Support\Contracts;

/**
 * Загрузка тенанта хоста по logical id из тикета.
 * null — single-tenant или тикет без арендатора.
 */
interface SupportTenantResolver
{
    public function resolve(int|string|null $id): ?SupportTenant;
}
