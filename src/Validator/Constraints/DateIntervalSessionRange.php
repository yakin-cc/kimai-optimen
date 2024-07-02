<?php

namespace App\Validator\Constraints;

use Symfony\Component\Validator\Constraint;

class DateIntervalSessionRange extends Constraint
{
    public $minSeconds;
    public $maxSeconds;
    public $minMessage = 'The value must be at least {{ limit }} seconds.';
    public $maxMessage = 'The value cannot exceed {{ limit }} seconds.';
}
