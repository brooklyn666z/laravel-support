<?php

declare(strict_types=1);

namespace Fillindev\Support\Http\Controllers;

use Fillindev\Support\Contracts\SupportableUser;
use Fillindev\Support\Exceptions\InvalidTicketTransition;
use Fillindev\Support\Http\Resources\TicketResource;
use Fillindev\Support\Http\TicketPresenter;
use Fillindev\Support\Models\Ticket;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Routing\Controller;
use InvalidArgumentException;

abstract class SupportController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        protected readonly TicketPresenter $presenter,
    ) {}

    protected function actor(): SupportableUser
    {
        $user = request()->user();

        if (! $user instanceof SupportableUser) {
            abort(403);
        }

        return $user;
    }

    protected function ticketResponse(Ticket $ticket, bool $publicMessagesOnly, int $status = 200): JsonResponse
    {
        return (new TicketResource($this->presenter->present($ticket, $publicMessagesOnly)))
            ->response()
            ->setStatusCode($status);
    }

    /**
     * @return list<UploadedFile>
     */
    protected function uploadedFiles(FormRequest $request): array
    {
        $files = $request->file('attachments');

        if ($files === null) {
            return [];
        }

        return is_array($files) ? array_values($files) : [$files];
    }

    protected function attempt(callable $callback): JsonResponse
    {
        try {
            return $callback();
        } catch (InvalidArgumentException|InvalidTicketTransition $exception) {
            return response()->json([
                'errors' => [$exception->getMessage()],
            ], 422);
        }
    }
}
