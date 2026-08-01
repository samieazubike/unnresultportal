<?php

use App\Support\Grading;

/**
 * README §5: "Boundary tests are mandatory. Assert correct grade at
 * 39, 40, 44, 45, 49, 50, 59, 60, 69, 70. These exact values were named in
 * the project's testing section."
 */
describe('grade boundaries', function () {
    it('resolves the grade at every named boundary value', function (int $total, string $grade, int $point) {
        expect(Grading::forScore($total))->toBe(['grade' => $grade, 'grade_point' => $point]);
    })->with([
        'below 40 is F' => [39, 'F', 0],
        '40 is E' => [40, 'E', 1],
        '44 is E' => [44, 'E', 1],
        '45 is D' => [45, 'D', 2],
        '49 is D' => [49, 'D', 2],
        '50 is C' => [50, 'C', 3],
        '59 is C' => [59, 'C', 3],
        '60 is B' => [60, 'B', 4],
        '69 is B' => [69, 'B', 4],
        '70 is A' => [70, 'A', 5],
    ]);

    it('handles the extremes of the scale', function () {
        expect(Grading::forScore(0))->toBe(['grade' => 'F', 'grade_point' => 0])
            ->and(Grading::forScore(100))->toBe(['grade' => 'A', 'grade_point' => 5]);
    });

    it('resolves fractional scores by the band they fall in', function () {
        expect(Grading::grade(39.99))->toBe('F')
            ->and(Grading::grade(40.01))->toBe('E')
            ->and(Grading::grade(44.5))->toBe('E')
            ->and(Grading::grade(69.99))->toBe('B');
    });

    it('rejects scores outside 0 to 100', function (float $total) {
        expect(fn () => Grading::forScore($total))->toThrow(InvalidArgumentException::class);
    })->with([[-1], [100.01], [150]]);
});

describe('total score', function () {
    it('sums the CA and exam components', function () {
        expect(Grading::total(24, 46))->toBe(70.0)
            ->and(Grading::total(0, 0))->toBe(0.0)
            ->and(Grading::total(30, 70))->toBe(100.0);
    });

    it('caps the components at the documented maxima', function () {
        expect(Grading::MAX_CA_SCORE)->toBe(30)
            ->and(Grading::MAX_EXAM_SCORE)->toBe(70)
            ->and(Grading::MAX_CA_SCORE + Grading::MAX_EXAM_SCORE)->toBe(Grading::MAX_TOTAL_SCORE);
    });
});

describe('classification', function () {
    it('maps a final CGPA to its degree class', function (float $cgpa, string $class) {
        expect(Grading::classification($cgpa))->toBe($class);
    })->with([
        [4.50, 'First Class Honours'],
        [4.49, 'Second Class Honours (Upper Division)'],
        [3.50, 'Second Class Honours (Upper Division)'],
        [3.49, 'Second Class Honours (Lower Division)'],
        [2.40, 'Second Class Honours (Lower Division)'],
        [2.39, 'Third Class Honours'],
        [1.50, 'Third Class Honours'],
        [1.00, 'Pass'],
        [0.99, 'Fail'],
    ]);
});

it('exposes every grade letter, highest first', function () {
    expect(Grading::grades())->toBe(['A', 'B', 'C', 'D', 'E', 'F']);
});

it('treats only F as failing', function () {
    expect(Grading::isFailing('F'))->toBeTrue()
        ->and(Grading::isFailing('E'))->toBeFalse();
});
