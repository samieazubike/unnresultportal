<?php

namespace App\Http\Requests\Admin;

use App\Support\AcademicLevel;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * The courses pre-selected for one department and level in a session and
 * semester. An empty list is allowed: it clears the offering.
 */
class SaveCourseOfferingRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'dept_id' => ['required', 'integer', 'exists:departments,id'],
            'level' => ['required', 'integer', Rule::in(AcademicLevel::LEVELS)],
            'session' => ['required', 'string', 'regex:/^\d{4}\/\d{4}$/'],
            'semester' => ['required', 'integer', 'in:1,2'],
            'course_ids' => ['present', 'array'],
            'course_ids.*' => ['integer', 'distinct', 'exists:courses,id'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'session.regex' => 'The session must look like 2025/2026.',
        ];
    }
}
