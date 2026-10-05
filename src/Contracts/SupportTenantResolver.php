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

    /**
     * Текущий тенант из контекста запроса хоста, без id в URL.
     * null — хост не выделил арендатора, тикет создаётся без tenant.
     */
    public function current(): ?SupportTenant;
}
