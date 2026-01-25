<?php

declare(strict_types=1);

namespace Modules\EventRegistrations\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class RegisterToEventRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, array<string>>
     */
    public function rules(): array
    {
        return [
            'form_data' => ['sometimes', 'array'],
            'form_data.*' => ['nullable', 'string', 'max:1000'],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'form_data.array' => __('event-registrations::messages.validation.form_data_array'),
            'notes.max' => __('event-registrations::messages.validation.notes_max'),
        ];
    }
}
