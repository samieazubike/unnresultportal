<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Opening or closing course registration for a session and semester.
 */
class UpdateRegistrationWindowRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'session' => ['required', 'string', 'regex:/^\d{4}\/\d{4}$/'],
            'semester' => ['required', 'integer', 'in:1,2'],
            'is_open' => ['required', 'boolean'],
        ];
    }
}
