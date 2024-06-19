<?php

/*
 * This file is part of the Kimai time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Reporting\ProjectDetails;

use App\Entity\Project;
use App\Entity\User;
use App\Model\ActivityStatistic;
use App\Model\BudgetStatisticModel;
use App\Model\Statistic\UserYear;
use App\Model\Statistic\Year;
use App\Model\UserStatistic;
use DateTime;

final class ProjectDetailsModel
{
    /**
     * @var Project
     */
    private $project;
    /**
     * @var Year[]
     */
    private $years = [];
    /**
     * @var array<string, array<ActivityStatistic>>
     */
    private $yearlyActivities = [];

    /**
     * @var array<string, array<ActivityStatistic>>
     */
    private $monthlyActivities = [];

    /**
     * @var array<string, array<int, UserYear>>
     */
    private $usersMonthly = [];
    /**
     * @var ActivityStatistic[]
     */
    private $activities = [];
    /**
     * @var BudgetStatisticModel
     */
    private $budgetStatisticModel;

    private $userActivities = [];

    public function __construct(Project $project)
    {
        $this->project = $project;
    }

    public function getProject(): Project
    {
        return $this->project;
    }

    public function addActivity(ActivityStatistic $activityStatistic): void
    {
        $this->activities[$activityStatistic->getActivity()->getId()] = $activityStatistic;
    }

    /**
     * @return ActivityStatistic[]
     */
    public function getActivities(?User $user = null): array
    {
        $activities = array_values($this->activities);
        return $activities;
    }

    public function addYearActivity(string $year, ActivityStatistic $activityStatistic): void
    {
        $this->yearlyActivities[$year][] = $activityStatistic;
    }

    /**
     * @param string $year
     * @return ActivityStatistic[]
     */
    public function getYearActivities(string $year): ?array
    {
        if (!\array_key_exists($year, $this->yearlyActivities)) {
            return [];
        }

        return $this->yearlyActivities[$year];
    }

    public function addMonthActivity(string $year, string $month, ActivityStatistic $activityStatistic): void
    {
        $this->monthlyActivities[$year][$month][] = $activityStatistic;
    }

    public function getMonthActivities(?DateTime $date = null, ?User $user = null): ?array
    {
        if ($date !== null) {
            $year = $date->format('Y');
            $month = $date->format('m');

            if (!array_key_exists($year, $this->monthlyActivities) || !array_key_exists($month, $this->monthlyActivities[$year])) {
                return null;
            }

            $activities = $this->monthlyActivities[$year][$month];
        } else {
            $activities = $this->monthlyActivities;
        }

        return $activities;
    }

    /**
     * @return UserStatistic[]
     */
    public function getUserStats(?DateTime $date = null): array
    {
        $users = [];
        $yearFilter = $date ? $date->format('Y') : null;
        $monthFilter = $date ? (int)$date->format('m') : null;

        foreach ($this->usersMonthly as $year => $userYears) {
            // Skip years that don't match the filter, if a filter is provided
            if ($yearFilter && $yearFilter != $year) {
                continue;
            }

            foreach ($userYears as $id => $userYear) {
                // Get duration and rate for the specified month or for all months if no month is specified
                $duration = $monthFilter ? $userYear->getDurationForMonth($monthFilter) : $userYear->getDuration();
                $rate = $monthFilter ? $userYear->getRateForMonth($monthFilter) : $userYear->getRate();

                // Ensure userStat is correctly instantiated and accumulated
                if (!array_key_exists($id, $users)) {
                    $users[$id] = new UserStatistic($userYear->getUser());
                }

                $userStat = $users[$id];
                $userStat->setRecordDuration($userStat->getRecordDuration() + $duration);
                $userStat->setRecordRate($userStat->getRecordRate() + $rate);
            }
        }

        // Filter out users with zero duration
        $users = array_filter($users, function($userStat) {
            return $userStat->getRecordDuration() > 0;
        });

        return $users;
    }
    public function setUserYear(Year $year, User $user): void
    {
        $this->usersMonthly[$year->getYear()][$user->getId()] = new UserYear($user, $year);
    }

    public function getUserYear(string $year, User $user): ?Year
    {
        if (!\array_key_exists($year, $this->usersMonthly) || !\array_key_exists($user->getId(), $this->usersMonthly[$year])) {
            return null;
        }

        return $this->usersMonthly[$year][$user->getId()]->getYear();
    }

    /**
     * @param string $year
     * @return UserYear[]
     */
    public function getUserYears(string $year): array
    {
        if (!\array_key_exists($year, $this->usersMonthly)) {
            return [];
        }

        return $this->usersMonthly[$year];
    }

    /**
     * @return Year[]
     */
    public function getYears(): array
    {
        return $this->years;
    }

    public function getYear(string $year): ?Year
    {
        foreach ($this->years as $tmp) {
            if ($tmp->getYear() === $year) {
                return $tmp;
            }
        }

        return null;
    }

    /**
     * @param Year[] $years
     */
    public function setYears(array $years): void
    {
        $all = [];
        foreach ($years as $year) {
            $all[$year->getYear()] = $year;
        }
        ksort($all);
        $this->years = array_values($all);
    }

    public function getBudgetStatisticModel(): ?BudgetStatisticModel
    {
        return $this->budgetStatisticModel;
    }

    public function setBudgetStatisticModel(BudgetStatisticModel $budgetStatisticModel): void
    {
        $this->budgetStatisticModel = $budgetStatisticModel;
    }

    public function addUserActivity(?DateTime $date, ?User $user, ?ActivityStatistic $activity)
    {
        $yearFilter = $date ? $date->format('Y') : null;
        $monthFilter = $date ? $date->format('m') : null;
        $userId = $user->getId();

        if (!isset($this->userActivities[$yearFilter][$monthFilter][$userId])) {
            $this->userActivities[$yearFilter][$monthFilter][$userId] = [];
        }

        $this->userActivities[$yearFilter][$monthFilter][$userId][] = $activity;
    }

    public function getUserActivities(?DateTime $date, ?User $user)
    {
        $yearFilter = $date ? $date->format('Y') : null;
        $monthFilter = $date ? $date->format('m') : null;
        $userId = $user->getId();

        return isset($this->userActivities[$yearFilter][$monthFilter][$userId]) ?
            $this->userActivities[$yearFilter][$monthFilter][$userId] : [];
    }
}
