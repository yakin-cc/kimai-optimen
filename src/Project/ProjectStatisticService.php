<?php

/*
 * This file is part of the Kimai time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Project;

use DateTime;
use App\Entity\User;
use App\Entity\Project;
use App\Entity\Activity;
use App\Entity\Timesheet;
use App\Model\Statistic\Day;
use App\Model\UserStatistic;
use App\Form\Model\DateRange;
use App\Model\Statistic\Year;
use App\Model\Statistic\Month;
use Doctrine\DBAL\Types\Types;
use App\Model\ProjectStatistic;
use App\Model\ActivityStatistic;
use Doctrine\ORM\Query\Expr\Join;
use App\Repository\UserRepository;
use App\Timesheet\DateTimeFactory;
use App\Event\ProjectStatisticEvent;
use App\Repository\ProjectRepository;
use App\Repository\ActivityRepository;
use App\Repository\TimesheetRepository;
use App\Repository\Loader\ProjectLoader;
use App\Event\ProjectBudgetStatisticEvent;
use App\Model\ProjectBudgetStatisticModel;
use App\Reporting\ProjectView\ProjectViewModel;
use App\Reporting\ProjectView\ProjectViewQuery;
use App\Reporting\ProjectDetails\ProjectDetailsModel;
use App\Reporting\ProjectDetails\ProjectDetailsQuery;
use App\Reporting\ProjectInactive\ProjectInactiveQuery;
use App\Reporting\ProjectDateRange\ProjectDateRangeQuery;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * @final
 */
class ProjectStatisticService
{
    private $repository;
    private $timesheetRepository;
    private $dispatcher;
    private $userRepository;
    private $activityRepository;

    public function __construct(ProjectRepository $projectRepository, TimesheetRepository $timesheetRepository, EventDispatcherInterface $dispatcher, UserRepository $userRepository, ActivityRepository $activityRepository)
    {
        $this->repository = $projectRepository;
        $this->timesheetRepository = $timesheetRepository;
        $this->dispatcher = $dispatcher;
        $this->userRepository = $userRepository;
        $this->activityRepository = $activityRepository;
    }

    /**
     * WARNING: this method does not respect the budget type. Your results will always be wither the "full lifetime data" or the "selected date-range".
     *
     * @param Project $project
     * @param DateTime|null $begin
     * @param DateTime|null $end
     * @return ProjectStatistic
     */
    public function getProjectStatistics(Project $project, ?DateTime $begin = null, ?DateTime $end = null): ProjectStatistic
    {
        $statistics = $this->getBudgetStatistic([$project], $begin, $end);
        $event = new ProjectStatisticEvent($project, array_pop($statistics), $begin, $end);
        $this->dispatcher->dispatch($event);

        return $event->getStatistic();
    }

    /**
     * @param ProjectInactiveQuery $query
     * @return Project[]
     */
    public function findInactiveProjects(ProjectInactiveQuery $query): array
    {
        $user = $query->getUser();
        $lastChange = clone $query->getLastChange();
        $now = new DateTime('now', $lastChange->getTimezone());

        $qb2 = $this->repository->createQueryBuilder('t1');
        $qb2
            ->select('1')
            ->from(Timesheet::class, 't')
            ->andWhere('p = t.project')
            ->andWhere($qb2->expr()->gte('t.begin', ':begin'))
        ;

        $qb = $this->repository->createQueryBuilder('p');
        $qb
            ->select('p, c')
            ->leftJoin('p.customer', 'c')
            ->andWhere($qb->expr()->eq('p.visible', true))
            ->andWhere($qb->expr()->eq('c.visible', true))
            ->andWhere($qb->expr()->not($qb->expr()->exists($qb2)))
            ->andWhere(
                $qb->expr()->orX(
                    $qb->expr()->isNull('p.end'),
                    $qb->expr()->gte('p.end', ':project_end')
                )
            )
            ->setParameter('project_end', $now, Types::DATETIME_MUTABLE)
            ->setParameter('begin', $lastChange, Types::DATETIME_MUTABLE)
        ;

        $this->repository->addPermissionCriteria($qb, $user);

        /** @var Project[] $projects */
        $projects = $qb->getQuery()->getResult();

        // pre-cache customer objects instead of joining them
        $loader = new ProjectLoader($this->repository->createQueryBuilder('p')->getEntityManager());
        $loader->loadResults($projects);

        return $projects;
    }

    /**
     * @param ProjectDateRangeQuery $query
     * @return Project[]
     */
    public function findProjectsForDateRange(ProjectDateRangeQuery $query, DateRange $dateRange): array
    {
        $user = $query->getUser();
        $begin = $dateRange->getBegin();
        $end = $dateRange->getEnd();

        $qb = $this->repository->createQueryBuilder('p');
        $qb
            ->select('p')
            ->leftJoin('p.customer', 'c')
            ->andWhere($qb->expr()->eq('p.visible', true))
            ->andWhere($qb->expr()->eq('c.visible', true))
            ->andWhere(
                $qb->expr()->andX(
                    $qb->expr()->orX(
                        $qb->expr()->lte('p.start', ':end'),
                        $qb->expr()->isNull('p.start')
                    ),
                    $qb->expr()->orX(
                        $qb->expr()->gte('p.end', ':begin'),
                        $qb->expr()->isNull('p.end')
                    )
                )
            )
            ->setParameter('begin', $begin, Types::DATETIME_MUTABLE)
            ->setParameter('end', $end, Types::DATETIME_MUTABLE)
        ;

        if (!$query->isIncludeNoWork()) {
            $qb2 = $this->repository->createQueryBuilder('t1');
            $qb2
                ->select('1')
                ->from(Timesheet::class, 't')
                ->andWhere('p = t.project')
                ->andWhere($qb2->expr()->between('t.begin', ':begin', ':end'))
            ;
            $qb->andWhere($qb->expr()->exists($qb2));
        }

        if ($query->isIncludeNoBudget()) {
            $qb->andWhere(
                $qb->expr()->eq('p.budget', 0.0),
                $qb->expr()->eq('p.timeBudget', 0)
            );
        } elseif (!$query->isBudgetIndependent()) {
            $qb->andWhere(
                $qb->expr()->orX(
                    $qb->expr()->gt('p.budget', 0.0),
                    $qb->expr()->gt('p.timeBudget', 0)
                )
            );
            if ($query->isBudgetTypeMonthly()) {
                $qb->andWhere(
                    $qb->expr()->eq('p.budgetType', ':typeMonth')
                );
                $qb->setParameter('typeMonth', 'month');
            } else {
                $qb->andWhere(
                    $qb->expr()->isNull('p.budgetType')
                );
            }
        }

        if ($query->getCustomer() !== null) {
            $qb->andWhere($qb->expr()->eq('p.customer', ':customer'))
                ->setParameter('customer', $query->getCustomer());
        }

        $this->repository->addPermissionCriteria($qb, $user);

        /** @var Project[] $projects */
        $projects = $qb->getQuery()->getResult();

        // pre-cache customer objects instead of joining them
        $loader = new ProjectLoader($this->repository->createQueryBuilder('p')->getEntityManager());
        $loader->loadResults($projects);

        return $projects;
    }

    public function getBudgetStatisticModel(Project $project, DateTime $today): ProjectBudgetStatisticModel
    {
        $stats = new ProjectBudgetStatisticModel($project);
        $stats->setStatisticTotal($this->getProjectStatistics($project));

        $begin = null;
        $end = $today;

        if ($project->isMonthlyBudget()) {
            $dateFactory = new DateTimeFactory($today->getTimezone());
            $begin = $dateFactory->getStartOfMonth($today);
            $end = $dateFactory->getEndOfMonth($today);
        }

        $stats->setStatistic($this->getProjectStatistics($project, $begin, $end));

        return $stats;
    }

    /**
     * @param Project[] $projects
     * @param DateTime $today
     * @return ProjectBudgetStatisticModel[]
     */
    public function getBudgetStatisticModelForProjects(array $projects, DateTime $today): array
    {
        $models = [];
        $monthly = [];
        $allTime = [];

        foreach ($projects as $project) {
            $models[$project->getId()] = new ProjectBudgetStatisticModel($project);
            if ($project->isMonthlyBudget()) {
                $monthly[] = $project;
            } else {
                $allTime[] = $project;
            }
        }

        $statisticsTotal = $this->getBudgetStatistic($projects);
        foreach ($statisticsTotal as $id => $statistic) {
            $models[$id]->setStatisticTotal($statistic);
        }

        $dateFactory = new DateTimeFactory($today->getTimezone());

        $begin = null;
        $end = $today;

        if (\count($monthly) > 0) {
            $begin = $dateFactory->getStartOfMonth($today);
            $end = $dateFactory->getEndOfMonth($today);
            $statistics = $this->getBudgetStatistic($monthly, $begin, $end);
            foreach ($statistics as $id => $statistic) {
                $models[$id]->setStatistic($statistic);
            }
        }

        if (\count($allTime) > 0) {
            // display the budget at the end of the selected period and not the total sum of all times (do not include times in the future)
            $statistics = $this->getBudgetStatistic($allTime, null, $today);
            foreach ($statistics as $id => $statistic) {
                $models[$id]->setStatistic($statistic);
            }
        }

        $event = new ProjectBudgetStatisticEvent($models, $begin, $end);
        $this->dispatcher->dispatch($event);

        return $models;
    }

    /**
     * @param Project[] $projects
     * @param DateTime $begin
     * @param DateTime $end
     * @param DateTime|null $totalsEnd
     * @return ProjectBudgetStatisticModel[]
     */
    public function getBudgetStatisticModelForProjectsByDateRange(array $projects, DateTime $begin, DateTime $end, ?DateTime $totalsEnd = null): array
    {
        $models = [];

        foreach ($projects as $project) {
            $models[$project->getId()] = new ProjectBudgetStatisticModel($project);
        }

        $statisticsTotal = $this->getBudgetStatistic($projects, null, $totalsEnd);
        foreach ($statisticsTotal as $projectId => $statistic) {
            $models[$projectId]->setStatisticTotal($statistic);
        }

        $statistics = $this->getBudgetStatistic($projects, $begin, $end);
        foreach ($statistics as $projectId => $statistic) {
            $models[$projectId]->setStatistic($statistic);
        }

        $event = new ProjectBudgetStatisticEvent($models, $begin, $end);
        $this->dispatcher->dispatch($event);

        return $models;
    }

    /**
     * @param Project[] $projects
     * @param DateTime|null $begin
     * @param DateTime|null $end
     * @return array<int, ProjectStatistic>
     */
    public function getBudgetStatistic(array $projects, ?DateTime $begin = null, ?DateTime $end = null): array
    {
        $statistics = [];
        foreach ($projects as $project) {
            $statistics[$project->getId()] = new ProjectStatistic();
        }

        $qb = $this->timesheetRepository->createQueryBuilder('t');
        $qb
            ->select('IDENTITY(t.project) AS id')
            ->addSelect('COALESCE(SUM(t.duration), 0) as duration')
            ->addSelect('COALESCE(SUM(t.rate), 0) as rate')
            ->addSelect('COALESCE(SUM(t.internalRate), 0) as internalRate')
            ->addSelect('COUNT(t.id) as counter')
            ->addSelect('t.billable as billable')
            ->addSelect('t.exported as exported')
            ->andWhere($qb->expr()->in('t.project', ':project'))
            ->andWhere($qb->expr()->isNotNull('t.end'))
            ->groupBy('id')
            ->addGroupBy('billable')
            ->addGroupBy('exported')
            ->setParameter('project', array_keys($statistics))
        ;

        if ($begin !== null) {
            $qb
                ->andWhere($qb->expr()->gte('t.begin', ':begin'))
                ->setParameter('begin', $begin, Types::DATETIME_MUTABLE)
            ;
        }

        if ($end !== null) {
            $qb
                ->andWhere($qb->expr()->lte('t.begin', ':end'))
                ->setParameter('end', $end, Types::DATETIME_MUTABLE)
            ;
        }

        $result = $qb->getQuery()->getResult();

        if (null !== $result) {
            foreach ($result as $resultRow) {
                $statistic = $statistics[$resultRow['id']];
                $statistic->setDuration($statistic->getDuration() + $resultRow['duration']);
                $statistic->setRate($statistic->getRate() + $resultRow['rate']);
                $statistic->setInternalRate($statistic->getInternalRate() + $resultRow['internalRate']);
                $statistic->setCounter($statistic->getCounter() + $resultRow['counter']);
                if ($resultRow['billable']) {
                    $statistic->setDurationBillable($statistic->getDurationBillable() + $resultRow['duration']);
                    $statistic->setRateBillable($statistic->getRateBillable() + $resultRow['rate']);
                    $statistic->setInternalRateBillable($statistic->getInternalRateBillable() + $resultRow['internalRate']);
                    $statistic->setCounterBillable($statistic->getCounterBillable() + $resultRow['counter']);
                    if ($resultRow['exported']) {
                        $statistic->setDurationBillableExported($statistic->getDurationBillableExported() + $resultRow['duration']);
                        $statistic->setRateBillableExported($statistic->getRateBillableExported() + $resultRow['rate']);
                    }
                }
                if ($resultRow['exported']) {
                    $statistic->setDurationExported($statistic->getDurationExported() + $resultRow['duration']);
                    $statistic->setRateExported($statistic->getRateExported() + $resultRow['rate']);
                    $statistic->setInternalRateExported($statistic->getInternalRateExported() + $resultRow['internalRate']);
                    $statistic->setCounterExported($statistic->getCounterExported() + $resultRow['counter']);
                }
            }
        }

        return $statistics;
    }

    /**
    * @param ProjectDetailsQuery
    * @return ProjectDetailsModel
    */
    public function getProjectsDetails(ProjectDetailsQuery $query, int $projectId, ?DateTime $inputMonth = null, ?User $inputUser = null, ?Activity $inputActivity): ProjectDetailsModel
    {
        $project = $query->getProject();
        $model = new ProjectDetailsModel($project);
        $model->setBudgetStatisticModel($this->getBudgetStatisticModel($project, $query->getToday()));
        $qb = $this->timesheetRepository->createQueryBuilder('t');
        $qb
            ->select('COALESCE(SUM(t.duration), 0) as duration')
            ->addSelect('COALESCE(SUM(t.rate), 0) as rate')
            ->addSelect('COALESCE(SUM(t.internalRate), 0) as internalRate')
            ->addSelect('COUNT(t.id) as count')
            ->addSelect('t.billable as billable')
            ->andWhere('t.project = :project')
            ->setParameter('project', $query->getProject())
            ->addGroupBy('billable')
        ;

        // fetch stats grouped by ACTIVITY for all time
        $qb1 = clone $qb;
        $qb1
            ->leftJoin(Activity::class, 'a', Join::WITH, 'a.id = t.activity')
            ->addSelect('a as activity')
            ->addGroupBy('a')
        ;

        if ($inputUser !== null){
            $userId = $inputUser->getId();
            $qb1 ->andWhere('t.user = :userId')
                 ->setParameter('userId', $userId)
                ;
        }
        // Apply month filter if inputMonth is provided
        if ($inputMonth !== null) {
            $startOfMonth = clone $inputMonth;
            $endOfMonth = clone $inputMonth;
            $endOfMonth->modify('last day of this month')->setTime(23, 59, 59);

            $qb1->andWhere('t.begin BETWEEN :startOfMonth AND :endOfMonth')
                ->setParameter('startOfMonth', $startOfMonth)
                ->setParameter('endOfMonth', $endOfMonth)
                ;
        }

        /** @var array<ActivityStatistic> $activities */
        $activities = [];
        foreach ($qb1->getQuery()->getResult() as $tmp) {
            $activityId = $tmp['activity']->getId();
            if (!\array_key_exists($activityId, $activities)) {
                $activity = new ActivityStatistic();
                $activity->setActivity($tmp['activity']);
                $activities[$activityId] = $activity;
            } else {
                $activity = $activities[$activityId];
            }

            $activity->setRecordRate($activity->getRecordRate() + $tmp['rate']);
            $activity->setRecordDuration($activity->getRecordDuration() + $tmp['duration']);
            $activity->setInternalRate($activity->getInternalRate() + $tmp['internalRate']);
            $activity->setCounter($activity->getCounter() + $tmp['count']);

            if ($tmp['billable']) {
                $activity->setDurationBillable($activity->getDurationBillable() + $tmp['duration']);
                $activity->setRateBillable($activity->getRateBillable() + $tmp['rate']);
            }
        }

        foreach ($activities as $activity) {
            $model->addActivity($activity);
        }
        // ---------------------------------------------------
        //Fetch stats grouped by User for all time

        $qbUsers = clone $qb;
        $qbUsers 
            -> leftJoin(User::class, 'u', Join::WITH, 'u.id = t.user')
            ->addSelect('u as user')
            ->addGroupBy('u');

            // Apply month filter if inputMonth is provided
        if ($inputMonth !== null) {
            $startOfMonth = (clone $inputMonth)->modify('first day of this month')->setTime(0, 0, 0);
            $endOfMonth = (clone $inputMonth)->modify('last day of this month')->setTime(23, 59, 59);
            $qbUsers->andWhere('t.begin BETWEEN :startOfMonth AND :endOfMonth')
            ->setParameter('startOfMonth', $startOfMonth)
            ->setParameter('endOfMonth', $endOfMonth);
        }

        // Apply activity filter if inputActivity is provided
        if ($inputActivity !== null) {
            $qbUsers->andWhere('t.activity = :activityId')
            ->setParameter('activityId', $inputActivity->getId());
        }

        /** @var array<UserStatistic> $users */
        $users = [];
        foreach ($qbUsers->getQuery()->getResult() as $tmp) {
            $userId = $tmp['user']->getId();
            if (!array_key_exists($userId, $users)) {
                $userStatistic = new UserStatistic($tmp['user']);
                $users[$userId] = $userStatistic;
            } else {
                $userStatistic = $users[$userId];
            }

            $userStatistic->setRecordRate($userStatistic->getRecordRate() + $tmp['rate']);
            $userStatistic->setRecordDuration($userStatistic->getRecordDuration() + $tmp['duration']);
            $userStatistic->setInternalRate($userStatistic->getInternalRate() + $tmp['internalRate']);
            $userStatistic->setCounter($userStatistic->getCounter() + $tmp['count']);

            if ($tmp['billable']) {
                $userStatistic->setDurationBillable($userStatistic->getDurationBillable() + $tmp['duration']);
                $userStatistic->setRateBillable($userStatistic->getRateBillable() + $tmp['rate']);
            }
        }

        foreach ($users as $userStatistic) {
            $model->addUser($userStatistic);
        }

        // fetch stats grouped by YEAR, MONTH and USER
        $qb1 = clone $qb;
        $qb1
            ->addSelect('YEAR(t.date) as year')
            ->addSelect('MONTH(t.date) as month')
            ->addSelect('IDENTITY(t.user) as user')
            ->addGroupBy('year')
            ->addGroupBy('month')
            ->addGroupBy('user')
        ;

        $userMonths = $qb1->getQuery()->getResult();
        $userIds = array_unique(array_column($userMonths, 'user'));

        if (!empty($userIds)) {
            $qb2 = $this->userRepository->createQueryBuilder('u');
            $qb2->select('u')->where($qb2->expr()->in('u.id', $userIds));
            /** @var array<int, UserStatistic> $users */
            $users = [];
            foreach ($qb2->getQuery()->getResult() as $user) {
                $users[$user->getId()] = new UserStatistic($user);
            }

            foreach ($userMonths as $tmp) {
                $user = $users[$tmp['user']]->getUser();
                $year = $model->getUserYear($tmp['year'], $user);
                if ($year === null) {
                    $year = new Year($tmp['year']);
                    for ($i = 1; $i < 13; $i++) {
                        $year->setMonth(new Month($i));
                    }
                    $model->setUserYear($year, $user);
                }
                $month = $year->getMonth($tmp['month']);
                if ($month === null) {
                    $month = new Month($tmp['month']);
                    $year->setMonth($month);
                }
                $month->setTotalRate($month->getTotalRate() + $tmp['rate']);
                $month->setTotalDuration($month->getTotalDuration() + $tmp['duration']);
                $month->setTotalInternalRate($month->getTotalInternalRate() + $tmp['internalRate']);

                if ($tmp['billable']) {
                    $month->setBillableDuration($month->getBillableDuration() + $tmp['duration']);
                    $month->setBillableRate($month->getBillableRate() + $tmp['rate']);
                }
            }

            foreach ($users as $userId => $statistic) {
                foreach ($model->getYears() as $year) {
                    $statYear = $model->getUserYear($year->getYear(), $statistic->getUser());
                    if ($statYear === null) {
                        continue;
                    }
                    foreach ($statYear->getMonths() as $month) {
                        $statistic->addValuesFromMonth($month);
                    }
                }
            }
        }
        // ---------------------------------------------------

        $years = [];

        // make sure that we have all month between project start and end
        if ($project->getStart() !== null) {
            if ($project->getEnd() !== null) {
                $end = clone $project->getEnd();
            } else {
                $end = clone $query->getToday();
                $end->setDate((int) $end->format('Y'), 12, 31);
            }

            $start = clone $project->getStart();
            $start->setDate((int) $start->format('Y'), (int) $start->format('m'), 1);
            $start->setTime(0, 0, 0);

            while ($start !== false && $start < $end) {
                $year = $start->format('Y');
                if (!\array_key_exists($year, $years)) {
                    $years[$year] = new Year($year);
                }
                $tmp = $years[$year];
                $tmp->setMonth(new Month($start->format('m')));
                $start = $start->modify('+1 month');
            }
        }

        // fetch stats grouped by YEARS
        $qb1 = clone $qb;
        $qb1
            ->addSelect('YEAR(t.date) as year')
            ->addGroupBy('year')
        ;

        foreach ($qb1->getQuery()->getResult() as $year) {
            if (!\array_key_exists($year['year'], $years)) {
                $tmp = new Year($year['year']);
                for ($i = 1; $i < 13; $i++) {
                    $tmp->setMonth(new Month($i));
                }
                $years[$year['year']] = $tmp;
            } else {
                $tmp = $years[$year['year']];
            }
            $tmp->setTotalRate($tmp->getTotalRate() + $year['rate']);
            $tmp->setTotalInternalRate($tmp->getTotalInternalRate() + $year['internalRate']);
            $tmp->setTotalDuration($tmp->getTotalDuration() + $year['duration']);

            if ($year['billable']) {
                $tmp->setBillableDuration($tmp->getBillableDuration() + $year['duration']);
                $tmp->setBillableRate($tmp->getBillableRate() + $year['rate']);
            }
        }

        $yearActivities = [];
        foreach ($years as $yearName => $yearStat) {
            // fetch yearly stats grouped by ACTIVITY and YEAR
            $qb2 = clone $qb;
            $qb2
                ->leftJoin(Activity::class, 'a', Join::WITH, 'a.id = t.activity')
                ->addSelect('a as activity')
                ->addSelect('YEAR(t.date) as year')
                ->andWhere('YEAR(t.date) = :year')
                ->setParameter('year', $yearName)
                ->addGroupBy('year')
                ->addGroupBy('a')
            ;

            foreach ($qb2->getQuery()->getResult() as $tmp) {
                $activityId = $tmp['activity']->getId();
                if (!\array_key_exists($yearName, $yearActivities)) {
                    $yearActivities[$yearName] = [];
                }
                if (!\array_key_exists($activityId, $yearActivities[$yearName])) {
                    $activity = new ActivityStatistic();
                    $activity->setActivity($tmp['activity']);
                    $yearActivities[$yearName][$activityId] = $activity;
                } else {
                    $activity = $yearActivities[$yearName][$activityId];
                }
                $activity->setRecordRate($activity->getRecordRate() + $tmp['rate']);
                $activity->setRecordDuration($activity->getRecordDuration() + $tmp['duration']);
                $activity->setInternalRate($activity->getInternalRate() + $tmp['internalRate']);
                $activity->setCounter($activity->getCounter() + $tmp['count']);

                if ($tmp['billable']) {
                    $activity->setDurationBillable($activity->getDurationBillable() + $tmp['duration']);
                    $activity->setRateBillable($activity->getRateBillable() + $tmp['rate']);
                }
            }
        }

        foreach ($yearActivities as $year => $activities) {
            foreach ($activities as $activity) {
                $model->addYearActivity($year, $activity);
            }
        }

        $model->setYears(array_values($years));
        // ---------------------------------------------------

        // fetch stats grouped by MONTH and YEAR
        $qb1 = clone $qb;
        $qb1
            ->addSelect('YEAR(t.date) as year')
            ->addSelect('MONTH(t.date) as month')
            ->addGroupBy('year')
            ->addGroupBy('month')
        ;
        foreach ($qb1->getQuery()->getResult() as $month) {
            $tmp = $model->getYear($month['year'])->getMonth($month['month']);
            if ($tmp === null) {
                $tmp = new Month($month['month']);
                $model->getYear($month['year'])->setMonth($tmp);
            }
            $tmp->setTotalRate($tmp->getTotalRate() + $month['rate']);
            $tmp->setTotalInternalRate($tmp->getTotalInternalRate() + $month['internalRate']);
            $tmp->setTotalDuration($tmp->getTotalDuration() + $month['duration']);

            if ($month['billable']) {
                $tmp->setBillableDuration($tmp->getBillableDuration() + $month['duration']);
                $tmp->setBillableRate($tmp->getBillableRate() + $month['rate']);
            }
        }
        // ---------------------------------------------------
        if ($inputMonth !== null) {
            $startOfMonth = (clone $inputMonth)->modify('first day of this month')->setTime(0, 0, 0);
            dump($startOfMonth);
            $endOfMonth = (clone $inputMonth)->modify('last day of this month')->setTime(23, 59, 59);
            dump($endOfMonth);
        
            $qbDays = $this->timesheetRepository->createQueryBuilder('t');
            $qbDays
                ->select('DATE(t.begin) as day, COALESCE(SUM(t.duration), 0) as duration, COALESCE(SUM(t.rate), 0) as rate, t.billable as billable')
                ->andWhere('t.project = :project')
                ->andWhere('t.begin BETWEEN :startOfMonth AND :endOfMonth')
                ->setParameter('project', $project)
                ->setParameter('startOfMonth', $startOfMonth)
                ->setParameter('endOfMonth', $endOfMonth)
                ->groupBy('day, billable');
        
            // Execute the query and fetch results
            $results = $qbDays->getQuery()->getResult();
        
            // Initialize an array to track existing days
            $existingDays = [];
        
            foreach ($results as $result) {
                $day = new Day(new DateTime($result['day']), $result['duration'], $result['rate']);
        
                // If the day is billable, set the total duration billable
                if ($result['billable']) {
                    $day->setTotalDurationBillable($result['duration']);
                }
        
                // Add day to the model and track its existence
                $model->addDay($day);
                $existingDays[$result['day']] = true; // Mark this day as existing
            }
        
            // Add empty days for days that have no data
            $currentDay = (clone $startOfMonth);
            $lastDay = (clone $endOfMonth);
            
            while ($currentDay <= $lastDay) {
                $currentDayString = $currentDay->format('Y-m-d');
                
                if (!isset($existingDays[$currentDayString])) {
                    // Create a new Day object with empty values
                    $emptyDay = new Day(clone $currentDay, 0, 0);
                    $model->addDay($emptyDay);
                }
                
                // Move to the next day
                $currentDay->modify('+1 day');
            }
        }

        return $model;
    }

    /**
     * @param ProjectViewQuery $query
     * @return Project[]
     */
    public function findProjectsForView(ProjectViewQuery $query): array
    {
        $user = $query->getUser();
        $today = clone $query->getToday();

        $qb = $this->repository->createQueryBuilder('p');
        $qb
            ->select('p')
            ->leftJoin('p.customer', 'c')
            ->andWhere($qb->expr()->eq('p.visible', true))
            ->andWhere($qb->expr()->eq('c.visible', true))
            ->andWhere(
                $qb->expr()->orX(
                    $qb->expr()->isNull('p.end'),
                    $qb->expr()->gte('p.end', ':project_end')
                )
            )
            ->addGroupBy('p')
            ->setParameter('project_end', $today, Types::DATETIME_MUTABLE)
        ;

        if ($query->getCustomer() !== null) {
            $qb->andWhere($qb->expr()->eq('c', ':customer'));
            $qb->setParameter('customer', $query->getCustomer()->getId());
        }

        if (!$query->isIncludeNoWork()) {
            $qb
                ->leftJoin(Timesheet::class, 't', 'WITH', 'p.id = t.project')
                ->andHaving($qb->expr()->gt('SUM(t.duration)', 0))
            ;
        }

        if (!$query->isIncludeNoBudget()) {
            $qb->andWhere(
                $qb->expr()->orX(
                    $qb->expr()->gt('p.timeBudget', 0),
                    $qb->expr()->gt('p.budget', 0)
                )
            );
        }

        $this->repository->addPermissionCriteria($qb, $user);

        /** @var Project[] $projects */
        $projects = $qb->getQuery()->getResult();

        // pre-cache customer objects instead of joining them
        $loader = new ProjectLoader($this->repository->createQueryBuilder('p')->getEntityManager());
        $loader->loadResults($projects);

        return $projects;
    }

    /**
     * @param User $user
     * @param Project[] $projects
     * @param DateTime $today
     * @return ProjectViewModel[]
     */
    public function getProjectView(User $user, array $projects, DateTime $today, ?DateTime $inputMonth = null): array
    {
        $factory = DateTimeFactory::createByUser($user);
        $today = clone $today;
        $startOfWeek = $factory->getStartOfWeek($today);
        $endOfWeek = $factory->getEndOfWeek($today);
        $startMonth = (clone $startOfWeek)->modify('first day of this month');
        $endMonth = (clone $startOfWeek)->modify('last day of this month');
    
        $projectViews = [];
        foreach ($projects as $project) {
            $projectViews[$project->getId()] = new ProjectViewModel($project);
        }
    
        $budgetStats = $this->getBudgetStatisticModelForProjects($projects, $today);
        foreach ($budgetStats as $model) {
            $projectViews[$model->getProject()->getId()]->setBudgetStatisticModel($model);
        }
    
        $projectIds = array_keys($projectViews);
    
        $tplQb = $this->timesheetRepository->createQueryBuilder('t');
        $tplQb
            ->select('IDENTITY(t.project) AS id')
            ->addSelect('COUNT(t.id) as amount')
            ->addSelect('COALESCE(SUM(t.duration), 0) AS duration')
            ->addSelect('COALESCE(SUM(t.rate), 0) AS rate')
            ->andWhere($tplQb->expr()->in('t.project', ':project'))
            ->groupBy('t.project')
            ->setParameter('project', array_values($projectIds))
        ;
        
        // Apply month filter if inputMonth is provided
        if ($inputMonth !== null) {
            $startOfMonth = clone $inputMonth;
            $endOfMonth = clone $inputMonth;
            $endOfMonth->modify('last day of this month')->setTime(23, 59, 59);

            $tplQb->andWhere('t.begin BETWEEN :startOfMonth AND :endOfMonth')
            ->setParameter('startOfMonth', $startOfMonth)
            ->setParameter('endOfMonth', $endOfMonth);
        }

        $qb = clone $tplQb;
        $qb->addSelect('MAX(t.date) as lastRecord');
    
        $result = $qb->getQuery()->getScalarResult();
        foreach ($result as $row) {
            $projectViews[$row['id']]->setDurationTotal($row['duration']);
            $projectViews[$row['id']]->setRateTotal($row['rate']);
            $projectViews[$row['id']]->setTimesheetCounter($row['amount']);
            if ($row['lastRecord'] !== null) {
                // might be the wrong timezone
                $projectViews[$row['id']]->setLastRecord($factory->createDateTime($row['lastRecord']));
            }
        }
    
        // values for today
        $qb = clone $tplQb;
        $qb
            ->andWhere('DATE(t.date) = :start_date')
            ->setParameter('start_date', $today, Types::DATETIME_MUTABLE)
        ;
    
        $result = $qb->getQuery()->getScalarResult();
        foreach ($result as $row) {
            $projectViews[$row['id']]->setDurationDay($row['duration'] ?? 0);
        }
    
        // values for the current week
        $qb = clone $tplQb;
        $qb
            ->andWhere('DATE(t.date) BETWEEN :start_date AND :end_date')
            ->setParameter('start_date', $startOfWeek, Types::DATETIME_MUTABLE)
            ->setParameter('end_date', $endOfWeek, Types::DATETIME_MUTABLE)
        ;
    
        $result = $qb->getQuery()->getScalarResult();
        foreach ($result as $row) {
            $projectViews[$row['id']]->setDurationWeek($row['duration']);
        }
    
        // values for the current month
        $qb = clone $tplQb;
        $qb
            ->andWhere('DATE(t.date) BETWEEN :start_date AND :end_date')
            ->setParameter('start_date', $startMonth, Types::DATETIME_MUTABLE)
            ->setParameter('end_date', $endMonth, Types::DATETIME_MUTABLE)
        ;
    
        $result = $qb->getQuery()->getScalarResult();
        foreach ($result as $row) {
            $projectViews[$row['id']]->setDurationMonth($row['duration']);
        }
    
        // values for all time (not exported)
        $qb = clone $tplQb;
        $qb
            ->andWhere('t.exported = :exported')
            ->setParameter('exported', false, Types::BOOLEAN)
        ;
    
        $result = $qb->getQuery()->getScalarResult();
        foreach ($result as $row) {
            $projectViews[$row['id']]->setNotExportedDuration($row['duration']);
            $projectViews[$row['id']]->setNotExportedRate($row['rate']);
        }
    
        // values for all time (not exported and billable)
        $qb = clone $tplQb;
        $qb
            ->andWhere('t.exported = :exported')
            ->andWhere('t.billable = :billable')
            ->setParameter('exported', false, Types::BOOLEAN)
            ->setParameter('billable', true, Types::BOOLEAN)
        ;
    
        $result = $qb->getQuery()->getScalarResult();
        foreach ($result as $row) {
            $projectViews[$row['id']]->setNotBilledDuration($row['duration']);
            $projectViews[$row['id']]->setNotBilledRate($row['rate']);
        }
    
        // values for all time (none billable)
        $qb = clone $tplQb;
        $qb
            ->andWhere('t.billable = :billable')
            ->groupBy('t.project')
            ->setParameter('billable', true, Types::BOOLEAN)
        ;
    
        $result = $qb->getQuery()->getScalarResult();
        foreach ($result as $row) {
            $projectViews[$row['id']]->setBillableDuration($row['duration']);
            $projectViews[$row['id']]->setBillableRate($row['rate']);
        }
    
        return array_values($projectViews);
    }


    /**
     * 
     * @param Project
     * @return array
     */
    public function findMonthsForProject(int $projectId): array
    {
        $qb = $this->timesheetRepository->createQueryBuilder('t');
        $qb
            ->select('DISTINCT t.begin')
            ->where('t.project = :projectId')
            ->setParameter('projectId', $projectId)
            ->orderBy('t.begin', 'DESC');

        $result = $qb->getQuery()->getResult();

        $dateTimeResult = [];
        foreach ($result as $row) {
            $date = $row['begin'];
            if ($date instanceof DateTime) {
                $unformattedDate = (clone $date)->modify('first day of this month midnight');
                $formattedDate = $unformattedDate->format('F Y');
                $dateTimeResult[$formattedDate] = $unformattedDate;
            }
        }

        return $dateTimeResult;
    }


    public function findUsersForProject(int $projectId, ?DateTime $inputMonth = null): array
    {
        $qb = $this->timesheetRepository->createQueryBuilder('t');
        $qb
            ->select('u.id') // Select user IDs
            ->join('t.user', 'u')
            ->where('t.project = :projectId')
            ->setParameter('projectId', $projectId)
            ->groupBy('u.id') // Group by user ID to ensure uniqueness
            ->orderBy('u.alias', 'ASC');

        if ($inputMonth !== null) {
            $startOfMonth = clone $inputMonth;
            $endOfMonth = clone $inputMonth;
            $endOfMonth->modify('last day of this month')->setTime(23, 59, 59);

            $qb->andWhere('t.begin BETWEEN :startOfMonth AND :endOfMonth')
            ->setParameter('startOfMonth', $startOfMonth)
            ->setParameter('endOfMonth', $endOfMonth);
        }

            $userIds = $qb->getQuery()->getArrayResult(); // Get an array of user IDs

        // Now, fetch User entities based on IDs
        $result = $this->userRepository->findByIds($userIds);
    
        return $result;
    }

    public function findActivitiesForProject(int $projectId, ?DateTime $inputMonth = null): array
    {
        $qb = $this->timesheetRepository->createQueryBuilder('t');
        $qb
            ->select('a.id')
            ->join('t.activity', 'a') 
            ->where('t.project = :projectId')
            ->setParameter('projectId', $projectId)
            ->groupBy('a.id')
            ->orderBy('a.name', 'ASC');
        
        if ($inputMonth !== null) {
            $startOfMonth = clone $inputMonth;
            $endOfMonth = clone $inputMonth;
            $endOfMonth->modify('last day of this month')->setTime(23, 59, 59);

            $qb->andWhere('t.begin BETWEEN :startOfMonth AND :endOfMonth')
            ->setParameter('startOfMonth', $startOfMonth)
            ->setParameter('endOfMonth', $endOfMonth);
        }

        $activityIds = $qb->getQuery()->getArrayResult();
        $result = $this->activityRepository->findByIds($activityIds);
    
        return $result;
    }
}