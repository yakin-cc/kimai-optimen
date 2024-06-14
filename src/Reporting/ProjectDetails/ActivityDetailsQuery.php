<?php

namespace App\Reporting\ProjectDetails;

class ActivityDetailsQuery
{
    /**
     * @var string|null
     */
    private $activity;

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
