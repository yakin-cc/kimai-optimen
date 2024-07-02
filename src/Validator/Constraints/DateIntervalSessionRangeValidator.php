<?php

namespace App\Validator\Constraints;

use DateInterval;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

class DateIntervalSessionRangeValidator extends ConstraintValidator
{
    public function validate($value, Constraint $constraint)
    {
        if (!$constraint instanceof DateIntervalSessionRange) {
            throw new UnexpectedTypeException($constraint, DateIntervalSessionRange::class);
        }

        if (null === $value || '' === $value) {
            return;
        }

        if (!$value instanceof DateInterval) {
            throw new UnexpectedTypeException($value, DateInterval::class);
        }

        $totalSeconds = $value->s + ($value->i * 60) + ($value->h * 3600);
        
        if ($totalSeconds < $constraint->minSeconds) {
            $this->context->buildViolation($constraint->minMessage)
                ->setParameter('{{ limit }}', $constraint->minSeconds)
                ->addViolation();
            return;
        }

        if ($totalSeconds > $constraint->maxSeconds) {
            $this->context->buildViolation($constraint->maxMessage)
                ->setParameter('{{ limit }}', $constraint->maxSeconds)
                ->addViolation();
            return;
        }
    }
}
