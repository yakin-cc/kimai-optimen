<?php

/*
 * This file is part of the Kimai time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Configuration;

use App\Utils\MomentFormatConverter;

final class LanguageFormattings
{
    /**
     * @var array
     */
    private $settings;
    /**
     * @var MomentFormatConverter
     */
    private $momentFormatter;

    public function __construct(array $languageSettings)
    {
        $this->settings = $languageSettings;
        $this->momentFormatter = new MomentFormatConverter();
    }

    /**
     * Returns an array with all available locale/language codes.
     *
     * @return string[]
     */
    public function getAvailableLanguages(): array
    {
        return array_keys($this->settings);
    }

    /**
     * Returns the format which is used by the form component to handle date values.
     *
     * @param string $locale
     * @return string
     */
    public function getDateTypeFormat(string $locale): string
    {
        return $this->getConfig('date_type', $locale);
    }

    /**
     * Returns the format which is used by the Javascript component to handle date values.
     *
     * @param string $locale
     * @return string
     */
    public function getDatePickerFormat(string $locale): string
    {
        return $this->momentFormatter->convert($this->getDateTypeFormat($locale));
    }

    /**
     * Returns the locale specific date format, which should be used in combination with the twig filter "|date".
     *
     * @param string $locale
     * @return string
     */
    public function getDateFormat(string $locale): string
    {
        return $this->getConfig('date', $locale);
    }

    /**
     * Returns the locale specific time format, which should be used in combination with the twig filter "|time".
     *
     * @param string $locale
     * @return string
     */
    public function getTimeFormat(string $locale): string
    {
        return $this->getConfig('time', $locale);
    }

    /**
     * Returns the locale specific datetime format, which should be used in combination with the twig filter "|date".
     *
     * @param string $locale
     * @return string
     */
    public function getDateTimeFormat(string $locale): string
    {
        return $this->getConfig('date_time', $locale);
    }

    /**
     * Returns the format used in the "|duration" twig filter to display a Timesheet duration.
     *
     * @param string $locale
     * @return string
     */
    public function getDurationFormat(string $locale): string
    {
        return $this->getConfig('duration', $locale);
    }

    /**
     * @param string $key
     * @param string $locale
     * @return string
     */
    private function getConfig(string $key, string $locale): string
    {
        if (!isset($this->settings[$locale])) {
            throw new \InvalidArgumentException(sprintf('Unknown locale given: %s', $locale));
        }

        if (!isset($this->settings[$locale][$key])) {
            throw new \InvalidArgumentException(sprintf('Unknown setting for locale %s: %s', $locale, $key));
        }

        return $this->settings[$locale][$key];
    }
}
