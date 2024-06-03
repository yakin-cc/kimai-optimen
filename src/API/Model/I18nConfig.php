<?php

declare(strict_types=1);

/*
 * This file is part of the Kimai time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\API\Model;

use JMS\Serializer\Annotation as Serializer;

/**
 * @Serializer\ExclusionPolicy("all")
 */
final class I18nConfig
{
    /**
     * Format used for toolbar queries
     *
     * @var string
     *
     * @Serializer\Expose()
     * @Serializer\Groups({"Default"})
     * @Serializer\Type(name="string")
     * @phpstan-ignore-next-line
     */
    private $formDate = '';
    /**
     * Format used to display date-time values (see PHP function date_format)
     *
     * @var string
     *
     * @Serializer\Expose()
     * @Serializer\Groups({"Default"})
     * @Serializer\Type(name="string")
     * @phpstan-ignore-next-line
     */
    private $dateTime = '';
    /**
     * Format used to display date values (see PHP function date_format)
     *
     * @var string
     *
     * @Serializer\Expose()
     * @Serializer\Groups({"Default"})
     * @Serializer\Type(name="string")
     * @phpstan-ignore-next-line
     */
    private $date = '';
    /**
     * Format used to display times (see PHP function date_format)
     *
     * @var string
     *
     * @Serializer\Expose()
     * @Serializer\Groups({"Default"})
     * @Serializer\Type(name="string")
     * @phpstan-ignore-next-line
     */
    private $time = '';
    /**
     * Format used to display durations (replace: %h with hours, %m with minutes, %s with seconds)
     *
     * @var string
     *
     * @Serializer\Expose()
     * @Serializer\Groups({"Default"})
     * @Serializer\Type(name="string")
     * @phpstan-ignore-next-line
     */
    private $duration = '';
    /**
     * Whether a twenty-four hour format is used (true) or 12-hours AM/PM format (false)
     *
     * @var bool
     *
     * @Serializer\Expose()
     * @Serializer\Groups({"Default"})
     * @Serializer\Type(name="boolean")
     * @phpstan-ignore-next-line
     */
    private $is24hours = true;
    /**
     * The current time of the user
     *
     * @var \DateTime
     *
     * @Serializer\Expose()
     * @Serializer\Groups({"Default"})
     * @Serializer\Type(name="DateTime")
     * @phpstan-ignore-next-line
     */
    private $now;

    public function setNow(\DateTime $now): I18nConfig
    {
        $this->now = $now;

        return $this;
    }

    public function setFormDate(string $formDate): I18nConfig
    {
        $this->formDate = $formDate;

        return $this;
    }

    public function setDateTime(string $dateTime): I18nConfig
    {
        $this->dateTime = $dateTime;

        return $this;
    }

    public function setDate(string $date): I18nConfig
    {
        $this->date = $date;

        return $this;
    }

    public function setDuration(string $duration): I18nConfig
    {
        $this->duration = $duration;

        return $this;
    }

    public function setTime(string $time): I18nConfig
    {
        $this->time = $time;

        return $this;
    }

    public function setIs24hours(bool $is24hours): I18nConfig
    {
        $this->is24hours = $is24hours;

        return $this;
    }
}
