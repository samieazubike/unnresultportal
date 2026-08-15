<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * One student added to the roster by hand.
 *
 * No user account is involved: the row exists so the student can claim it.
 */
class StoreRosterStudentRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'reg_number' => ['required', 'string', 'max:20', 'unique:students,reg_number'],
            'dept_id' => ['required', 'integer', 'exists:departments,id'],
            'entry_year' => ['required', 'integer', 'min:1960', 'max:'.(date('Y') + 1)],
        ];
    }
}
