<?php

/*
 * This file is part of the Kimai time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Validator\Constraints;

use App\Entity\Timesheet as TimesheetEntity;
use App\Timesheet\TrackingModeService;
use Symfony\Component\Security\Core\Security;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

final class TimesheetRestartValidator extends ConstraintValidator
{
    private $trackingModeService;
    private $security;

    public function __construct(Security $security, TrackingModeService $service)
    {
        $this->security = $security;
        $this->trackingModeService = $service;
    }

    /**
     * @param TimesheetEntity $timesheet
     * @param Constraint $constraint
     */
    public function validate($timesheet, Constraint $constraint)
    {
        if (!($constraint instanceof TimesheetRestart)) {
            throw new UnexpectedTypeException($constraint, TimesheetRestart::class);
        }

        if (!\is_object($timesheet) || !($timesheet instanceof TimesheetEntity)) {
            throw new UnexpectedTypeException($timesheet, TimesheetEntity::class);
        }

        // special case that would otherwise need to be validated in several controllers:
        // an entry is edited and the end date is removed (or duration deleted) would restart the record,
        // which might be disallowed for the current user
        if (null !== $timesheet->getEnd()) {
            return;
        }

        if ($this->context->getViolations()->count() > 0) {
            return;
        }

        if (null !== $this->security->getUser() && $this->security->isGranted('start', $timesheet)) {
            return;
        }

        $mode = $this->trackingModeService->getActiveMode();
        $path = 'start';

        if ($mode->canEditEnd()) {
            $path = 'end';
        } elseif ($mode->canEditDuration()) {
            $path = 'duration';
        }

        $this->context->buildViolation('You are not allowed to start this timesheet record.')
            ->atPath($path)
            ->setTranslationDomain('validators')
            ->setCode(TimesheetRestart::START_DISALLOWED)
            ->addViolation();
    }
}
