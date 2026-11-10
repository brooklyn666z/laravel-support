<?php

declare(strict_types=1);

namespace Fillindev\Support\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PinMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'is_pinned' => ['sometimes', 'boolean'],
        ];
    }
}
