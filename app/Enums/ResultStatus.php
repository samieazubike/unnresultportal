<?php

namespace App\Enums;

/**
 * The result lifecycle from README §9:
 *
 *   draft ──submit──> submitted ──approve──> approved ──publish──> published
 *                          │
 *                          └──reject──> draft
 *
 * Approval means academically accepted. It does NOT make the result visible
 * to the student; publication is a separate deliberate action.
 */
enum ResultStatus: string
{
    case Draft = 'draft';
    case Submitted = 'submitted';
    case Approved = 'approved';
    case Published = 'published';

    public function label(): string
    {
        return ucfirst($this->value);
    }

    /**
     * Scores may only be written while the sheet is still with the lecturer.
     * From Approved onward the row is frozen for everyone, including the
     * lecturer who entered it.
     */
    public function allowsScoreEditing(): bool
    {
        return in_array($this, [self::Draft, self::Submitted], strict: true);
    }

    /**
     * Whether this status may legally transition into the given one.
     */
    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTransitions(), strict: true);
    }

    /**
     * @return list<self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Draft => [self::Submitted],
            self::Submitted => [self::Approved, self::Draft],
            self::Approved => [self::Published],
            self::Published => [],
        };
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
