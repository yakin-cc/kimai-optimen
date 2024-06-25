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
use App\Entity\Activity;
use DateTime;

final class ProjectDetailsQuery
{
    /**
     * @var Project|null
     */
    private $project;
    /**
     * @var DateTime
     */
    private $today;
    /**
     * @var User
     */
    private $user;

    /**
     * @var DateTime
     */
    private $month;

    /**
     * @var User
     */
    private $selectedUser;

    /**
     * @var Activity
     */
    private $activity;

    public function __construct(DateTime $today, User $user)
    {
        $this->today = $today;
        $this->user = $user;
        $this->month = null;
        $this->selectedUser = null;
        $this->activity = null;
    }

    public function getToday(): DateTime
    {
        return $this->today;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function getProject(): ?Project
    {
        return $this->project;
    }

    public function setProject(?Project $project): void
    {
        $this->project = $project;
    }

    public function getMonth(): ?DateTime
    {
        return $this->month;
    }

    public function setMonth(?DateTime $month): void
    {
        $this->month = $month;
    }

    /**
     * Get the value of user
     *
     * @return User
     */
    public function getSelectedUser(): ?User
    {
        return $this->selectedUser;
    }

    /**
     * Set the value of user
     *
     * @param User $selectedUser
     *
     * @return self
     */
    public function setSelectedUser(?User $selectedUser): self
    {
        $this->selectedUser = $selectedUser;

        return $this;
    }
     /**
     * Get the value of activity
     *
     * @return Activity
     */
    public function getActivity(): ?Activity
    {
        return $this->activity;
    }

    /**
     * Set the value of activity
     *
     * @param Activity $activity
     *
     * @return self
     */
    public function setActivity(?Activity $activity): self
    {
        $this->activity = $activity;

        return $this;
    }
}
