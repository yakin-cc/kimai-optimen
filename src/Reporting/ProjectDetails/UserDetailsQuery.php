<?php

namespace App\Reporting\ProjectDetails;

class UserDetailsQuery
{
    /**
     * @var string|null
     */
    private $user;

    /**
     * @var string|null
     */
    private $activity;

    /**
     * Get the value of user
     *
     * @return string|null
     */
    public function getUser(): ?string
    {
        return $this->user;
    }

    /**
     * Set the value of user
     *
     * @param string|null $user
     *
     * @return self
     */
    public function setUser(?string $user): self
    {
        $this->user = $user;

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
