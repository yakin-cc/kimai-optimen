<?php

/*
 * This file is part of the Kimai time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Utils;

/**
 * Convert duration strings into seconds.
 */
class Duration
{
    public const FORMAT_COLON = 'colon';
    public const FORMAT_NATURAL = 'natural';
    public const FORMAT_DECIMAL = 'decimal';

    /**
     * @deprecated since 1.13
     */
    public const FORMAT_SECONDS = 'seconds';
    /**
     * @deprecated since 1.21
     */
    public const FORMAT_WITH_SECONDS = '%h:%m';
    public const FORMAT_NO_SECONDS = '%h:%m';

    /**
     * Transforms seconds into a duration string.
     *
     * @param int|null $seconds
     * @param string $format
     * @return string|null
     */
    public function format(?int $seconds, string $format = self::FORMAT_NO_SECONDS)
    {
        if (null === $seconds) {
            return null;
        }

        if ($seconds < 0) {
            if ($seconds <= -60) {
                $format = '-' . $format;
            }
            $seconds = abs($seconds);
        }

        $hour = (int) floor($seconds / 3600);
        $minute = (int) floor((int) ($seconds / 60) % 60);

        $hour = $hour > 9 ? $hour : '0' . $hour;
        $minute = $minute > 9 ? $minute : '0' . $minute;
        $formatted = str_replace('%h', $hour, $format);

        return str_replace('%m', $minute, $formatted);
    }

    /**
     * Returns the seconds, which were given as $duration string.
     *
     * @param string $duration
     * @return int
     */
    public function parseDurationString(string $duration): int
    {
        if (false !== stripos($duration, ':')) {
            return $this->parseDuration($duration, self::FORMAT_COLON);
        }

        if (strpos($duration, '.') !== false || strpos($duration, ',') !== false) {
            return $this->parseDuration($duration, self::FORMAT_DECIMAL);
        }

        if (is_numeric($duration) && $duration == (int) $duration) {
            return $this->parseDuration($duration, self::FORMAT_DECIMAL);
        }

        return $this->parseDuration($duration, self::FORMAT_NATURAL);
    }

    /**
     * Returns the seconds, which were given as $mode formatted $duration string.
     *
     * @param string $duration
     * @param string $mode
     * @return int
     * @throws \InvalidArgumentException
     */
    public function parseDuration(string $duration, string $mode): int
    {
        if (empty($duration)) {
            return 0;
        }

        switch ($mode) {
            case self::FORMAT_COLON:
                $seconds = $this->parseColonFormat($duration);
                break;

            case self::FORMAT_NATURAL:
                $seconds = $this->parseNaturalFormat($duration);
                break;

            case self::FORMAT_DECIMAL:
                $seconds = $this->parseDecimalFormat($duration);
                break;

            case self::FORMAT_SECONDS:
                @trigger_error('Duration format FORMAT_SECONDS is deprecated and will be removed with 2.0', E_USER_DEPRECATED);
                $seconds = (int) $duration;
                break;

            default:
                throw new \InvalidArgumentException(sprintf('Unsupported duration format "%s"', $mode));
        }

        return $seconds;
    }

    protected function parseNaturalFormat(string $duration): int
    {
        try {
            $interval = new \DateInterval('PT' . strtoupper($duration));
            $reference = new \DateTimeImmutable();
            $endTime = $reference->add($interval);

            return $endTime->getTimestamp() - $reference->getTimestamp();
        } catch (\Exception $e) {
            throw new \InvalidArgumentException('Invalid input for natural format: ' . $duration);
        }
    }

    protected function parseDecimalFormat(string $duration): int
    {
        $duration = str_replace(',', '.', $duration);
        $duration = (float) $duration;
        $duration = $duration * 3600;

        return (int) $duration;
    }

    protected function parseColonFormat(string $duration): int
    {
        $parts = explode(':', $duration);
        if (\count($parts) < 2 || \count($parts) > 3) {
            throw new \InvalidArgumentException(
                sprintf('Invalid colon format given in "%s"', $duration)
            );
        }

        $i = 0;
        foreach ($parts as $part) {
            if (\strlen($part) === 0) {
                throw new \InvalidArgumentException(
                    sprintf('Colon format cannot parse "%s"', $duration)
                );
            }
            // the entire time could be negative
            if ($i++ > 0 && ((int) $part) < 0) {
                throw new \InvalidArgumentException(
                    sprintf('Negative input is not allowed in "%s"', $duration)
                );
            }
        }

        $seconds = 0;

        if (3 == \count($parts)) {
            $seconds += (int) array_pop($parts);
        }

        $seconds += (int) $parts[1] * 60;
        $seconds += abs((int) $parts[0] * 3600);

        if ($duration[0] === '-') {
            $seconds = $seconds * -1;
        }

        return $seconds;
    }
}
