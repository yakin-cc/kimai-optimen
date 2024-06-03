<?php

/*
 * This file is part of the Kimai time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Utils;

use App\Configuration\LanguageFormattings;
use App\Constants;

/**
 * Use this class, when you want information about formats for a locale.
 */
class LocaleFormats
{
    /**
     * @var LanguageFormattings
     */
    private $formats;
    /**
     * @var string
     */
    private $locale = Constants::DEFAULT_LOCALE;

    public function __construct(LanguageFormattings $formats, string $locale)
    {
        $this->formats = $formats;
        $this->locale = $locale;
    }

    /**
     * Returns an array with all available locale/language codes.
     *
     * @return string[]
     */
    public function getAvailableLanguages(): array
    {
        return $this->formats->getAvailableLanguages();
    }

    /**
     * Returns the current locale used by the user in this request.
     *
     * @return string
     */
    public function getLocale(): string
    {
        return $this->locale;
    }

    /**
     * Returns the format which is used by the form component to handle date values.
     *
     * @return string
     */
    public function getDateTypeFormat(): string
    {
        return $this->formats->getDateTypeFormat($this->getLocale());
    }

    /**
     * Returns the format which is used by the Javascript component to handle date values.
     *
     * @return string
     */
    public function getDatePickerFormat(): string
    {
        return $this->formats->getDatePickerFormat($this->getLocale());
    }

    /**
     * Returns the format which is used by the form component to handle datetime values.
     *
     * @deprecated since 1.16
     * @return string
     */
    public function getDateTimeTypeFormat(): string
    {
        return $this->formats->getDateTypeFormat($this->getLocale()) . ' HH:mm';
    }

    /**
     * Returns the locale specific date format, which should be used in combination with the twig filter "|date".
     *
     * @return string
     */
    public function getDateFormat(): string
    {
        return $this->formats->getDateFormat($this->getLocale());
    }

    /**
     * Returns the locale specific time format, which should be used in combination with the twig filter "|time".
     *
     * @return string
     */
    public function getTimeFormat(): string
    {
        return $this->formats->getTimeFormat($this->getLocale());
    }

    /**
     * Returns the locale specific datetime format, which should be used in combination with the twig filter "|date".
     *
     * @return string
     */
    public function getDateTimeFormat(): string
    {
        return $this->formats->getDateTimeFormat($this->getLocale());
    }

    /**
     * Returns the format used in the "|duration" twig filter to display a Timesheet duration.
     *
     * @return string
     */
    public function getDurationFormat(): string
    {
        return $this->formats->getDurationFormat($this->getLocale());
    }
}
