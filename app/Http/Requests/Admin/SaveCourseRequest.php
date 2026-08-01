<?php

namespace App\Http\Requests\Admin;

use App\Enums\UserRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Course details and teaching-team assignment.
 */
class SaveCourseRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $courseId = $this->route('course')?->id;

        return [
            'course_code' => ['required', 'string', 'max:20', Rule::unique('courses', 'course_code')->ignore($courseId)],
            'course_title' => ['required', 'string', 'max:255'],
            'credit_units' => ['required', 'integer', 'min:1', 'max:6'],
            'dept_id' => ['required', 'integer', 'exists:departments,id'],

            'lecturer_ids' => ['array', 'max:3'],
            'lecturer_ids.*' => [
                'integer',
                Rule::exists('users', 'id')->where('role', UserRole::Lecturer->value),
            ],

            'head_id' => [
                'nullable',
                'integer',
                Rule::exists('users', 'id')->where('role', UserRole::Lecturer->value),
            ],
        ];
    }

    /**
     * The head must be one of the assigned lecturers — the same rule the
     * database enforces with its composite foreign key, checked here so the
     * administrator gets a readable message instead of a constraint violation.
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                $team = $this->input('lecturer_ids', []);
                $head = $this->input('head_id');

                if ($head !== null && ! in_array((int) $head, array_map('intval', $team), strict: true)) {
                    $validator->errors()->add('head_id', 'The course head must be one of the assigned lecturers.');
                }

                if ($head === null && $team !== []) {
                    $validator->errors()->add('head_id', 'Choose which lecturer heads this course.');
                }
            },
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'lecturer_ids.max' => 'A course may have at most three lecturers.',
        ];
    }
}
