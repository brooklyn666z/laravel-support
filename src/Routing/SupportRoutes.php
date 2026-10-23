<?php

declare(strict_types=1);

namespace Fillindev\Support\Routing;

use Fillindev\Support\Http\Controllers\Admin\TicketController as AdminTicketController;
use Fillindev\Support\Http\Controllers\TicketController;
use Illuminate\Support\Facades\Route;

/**
 * HTTP-маршруты пакета. Хост вызывает register() сам:
 * провайдер пакета маршруты не подключает.
 */
final class SupportRoutes
{
    public static function register(): void
    {
        self::client();
        self::admin();
    }

    /**
     * Клиентские маршруты. $namePrefix нужен, когда хост регистрирует их повторно
     * для другой модели пользователя, например агента.
     */
    public static function client(string $namePrefix = 'support.'): void
    {
        Route::get('support/categories', [TicketController::class, 'categories'])->name($namePrefix.'categories');
        Route::get('support/tickets', [TicketController::class, 'index'])->name($namePrefix.'tickets.index');
        Route::get('support/tickets/unread', [TicketController::class, 'unread'])->name($namePrefix.'tickets.unread');
        Route::post('support/tickets', [TicketController::class, 'store'])->name($namePrefix.'tickets.store');
        Route::get('support/tickets/{ticket}', [TicketController::class, 'show'])->whereNumber('ticket')->name($namePrefix.'tickets.show');
        Route::post('support/tickets/{ticket}/messages', [TicketController::class, 'storeMessage'])->whereNumber('ticket')->name($namePrefix.'tickets.messages.store');
        Route::post('support/tickets/{ticket}/close', [TicketController::class, 'close'])->whereNumber('ticket')->name($namePrefix.'tickets.close');
    }

    public static function admin(): void
    {
        Route::get('support/admin/tickets', [AdminTicketController::class, 'index'])->name('support.admin.tickets.index');
        Route::get('support/admin/tickets/{ticket}', [AdminTicketController::class, 'show'])->whereNumber('ticket')->name('support.admin.tickets.show');
        Route::post('support/admin/tickets/{ticket}/messages', [AdminTicketController::class, 'storeMessage'])->whereNumber('ticket')->name('support.admin.tickets.messages.store');
        Route::post('support/admin/tickets/{ticket}/assign', [AdminTicketController::class, 'assign'])->whereNumber('ticket')->name('support.admin.tickets.assign');
        Route::post('support/admin/tickets/{ticket}/status', [AdminTicketController::class, 'changeStatus'])->whereNumber('ticket')->name('support.admin.tickets.status');
        Route::post('support/admin/tickets/{ticket}/priority', [AdminTicketController::class, 'changePriority'])->whereNumber('ticket')->name('support.admin.tickets.priority');
        Route::post('support/admin/tickets/{ticket}/close', [AdminTicketController::class, 'close'])->whereNumber('ticket')->name('support.admin.tickets.close');
        Route::post('support/admin/tickets/{ticket}/reopen', [AdminTicketController::class, 'reopen'])->whereNumber('ticket')->name('support.admin.tickets.reopen');
    }
}
