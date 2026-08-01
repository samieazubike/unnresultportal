<?php

namespace App\Enums;

/**
 * The five roles on the users.role enum, per README §3.
 *
 * Alumni are Student accounts whose programme is complete; there is
 * deliberately no separate role for them.
 */
enum UserRole: string
{
    case Student = 'student';
    case Lecturer = 'lecturer';
    case ExamOfficer = 'exam_officer';
    case IctDirector = 'ict_director';
    case Admin = 'admin';

    public function label(): string
    {
        return match ($this) {
            self::Student => 'Student',
            self::Lecturer => 'Lecturer',
            self::ExamOfficer => 'Examination Officer',
            self::IctDirector => 'ICT Director',
            self::Admin => 'Administrator',
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
