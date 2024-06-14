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
     * @var ?string|null
     */
    private $month;

    /**
     * @var string|null
     */
    private $selectedUser;

    /**
     * @var string|null
     */
    private $activity;

    public function __construct(DateTime $today, User $user)
    {
        $this->today = $today;
        $this->user = $user;
        $this->month = null;
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

    public function getMonth(): ?string
    {
        return $this->month;
    }

    public function setMonth(?string $month): void
    {
        $this->month = $month;
    }

    /**
     * Get the value of user
     *
     * @return string|null
     */
    public function getSelectedUser(): ?string
    {
        return $this->selectedUser;
    }

    /**
     * Set the value of user
     *
     * @param string|null $selectedUser
     *
     * @return self
     */
    public function setSelectedUser(?string $selectedUser): self
    {
        $this->selectedUser = $selectedUser;

        return $this;
    }
     /**
     * Get the value of activity
     *
     * @return string|null
     */
    public function getActivity(): ?string
    {
        return $this->activity;
    }

    /**
     * Set the value of activity
     *
     * @param string|null $activity
     *
     * @return self
     */
    public function setActivity(?string $activity): self
    {
        $this->activity = $activity;

        return $this;
    }
}
