<?php

namespace App\Enums;

/**
 * How a registration came about: pre-selected by an administrator through a
 * course offering, or added by the student during the registration window.
 */
enum RegistrationSource: string
{
    case Offering = 'offering';
    case Student = 'student';
}
