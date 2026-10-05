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
        Route::get('support/categories', [TicketController::class, 'categories'])->name('support.categories');
        Route::get('support/tickets', [TicketController::class, 'index'])->name('support.tickets.index');
        Route::post('support/tickets', [TicketController::class, 'store'])->name('support.tickets.store');
        Route::get('support/tickets/{ticket}', [TicketController::class, 'show'])->name('support.tickets.show');
        Route::post('support/tickets/{ticket}/messages', [TicketController::class, 'storeMessage'])->name('support.tickets.messages.store');
        Route::post('support/tickets/{ticket}/close', [TicketController::class, 'close'])->name('support.tickets.close');

        Route::get('support/admin/tickets', [AdminTicketController::class, 'index'])->name('support.admin.tickets.index');
        Route::get('support/admin/tickets/{ticket}', [AdminTicketController::class, 'show'])->name('support.admin.tickets.show');
        Route::post('support/admin/tickets/{ticket}/messages', [AdminTicketController::class, 'storeMessage'])->name('support.admin.tickets.messages.store');
        Route::post('support/admin/tickets/{ticket}/assign', [AdminTicketController::class, 'assign'])->name('support.admin.tickets.assign');
        Route::post('support/admin/tickets/{ticket}/status', [AdminTicketController::class, 'changeStatus'])->name('support.admin.tickets.status');
        Route::post('support/admin/tickets/{ticket}/priority', [AdminTicketController::class, 'changePriority'])->name('support.admin.tickets.priority');
        Route::post('support/admin/tickets/{ticket}/close', [AdminTicketController::class, 'close'])->name('support.admin.tickets.close');
        Route::post('support/admin/tickets/{ticket}/reopen', [AdminTicketController::class, 'reopen'])->name('support.admin.tickets.reopen');
    }
}
