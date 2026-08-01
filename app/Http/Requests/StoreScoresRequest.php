<?php

namespace App\Http\Requests;

use App\Support\Grading;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Typed score entry.
 *
 * Note what is absent: grade and grade_point. They are computed on save from
 * the two scores (README §10) and are never accepted from input, so they are
 * not validated here because they are never read here.
 */
class StoreScoresRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'scores' => ['required', 'array', 'min:1'],
            'scores.*.registration_id' => ['required', 'integer', 'exists:registrations,id'],
            'scores.*.ca_score' => ['required', 'numeric', 'min:0', 'max:'.Grading::MAX_CA_SCORE],
            'scores.*.exam_score' => ['required', 'numeric', 'min:0', 'max:'.Grading::MAX_EXAM_SCORE],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'scores.*.ca_score.max' => 'The CA score cannot exceed '.Grading::MAX_CA_SCORE.'.',
            'scores.*.exam_score.max' => 'The exam score cannot exceed '.Grading::MAX_EXAM_SCORE.'.',
        ];
    }
}
