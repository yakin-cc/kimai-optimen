<?php

/*
 * This file is part of the Kimai time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Timesheet\TrackingMode;

use App\Entity\Timesheet;
use App\Timesheet\RoundingService;
use DateTime;
use Symfony\Component\HttpFoundation\Request;

final class DefaultMode extends AbstractTrackingMode
{
    /**
     * @var RoundingService
     */
    private $rounding;

    public function __construct(RoundingService $rounding)
    {
        $this->rounding = $rounding;
    }

    public function canEditBegin(): bool
    {
        return true;
    }

    public function canEditEnd(): bool
    {
        return true;
    }

    public function canEditDuration(): bool
    {
        return true;
    }

    public function canUpdateTimesWithAPI(): bool
    {
        return true;
    }

    public function getId(): string
    {
        return 'default';
    }

    public function canSeeBeginAndEndTimes(): bool
    {
        return true;
    }

    public function create(Timesheet $timesheet, ?Request $request = null): void
    {
        parent::create($timesheet, $request);

        if (null === $timesheet->getBegin()) {
            $timesheet->setBegin(new DateTime('now', $this->getTimezone($timesheet)));
        }

        $this->rounding->roundBegin($timesheet);

        if (null !== $timesheet->getEnd()) {
            $this->rounding->roundEnd($timesheet);

            if (null !== $timesheet->getDuration()) {
                $this->rounding->roundDuration($timesheet);
            }
        }
    }
}
