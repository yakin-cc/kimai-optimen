<?php

/*
 * This file is part of the Kimai time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Invoice;

use App\Configuration\LanguageFormattings;
use App\Utils\LocaleFormatter;

final class DefaultInvoiceFormatter implements InvoiceFormatter
{
    private $locale;
    private $formats;
    /**
     * @var LocaleFormatter|null
     */
    private $formatter;

    public function __construct(LanguageFormattings $formats, string $locale)
    {
        $this->formats = $formats;
        $this->locale = $locale;
    }

    private function getFormatter(): LocaleFormatter
    {
        if ($this->formatter === null) {
            $this->formatter = new LocaleFormatter($this->formats, $this->locale);
        }

        return $this->formatter;
    }

    public function getFormattedDateTime(\DateTime $date): string
    {
        return $this->getFormatter()->dateShort($date);
    }

    public function getFormattedTime(\DateTime $date): string
    {
        return $this->getFormatter()->time($date);
    }

    public function getFormattedMonthName(\DateTime $date): string
    {
        return $this->getFormatter()->monthName($date);
    }

    public function getFormattedMoney(float $amount, ?string $currency, bool $withCurrency = true): string
    {
        return $this->getFormatter()->money($amount, $currency, $withCurrency);
    }

    public function getFormattedDuration(int $seconds): string
    {
        return $this->getFormatter()->duration($seconds);
    }

    public function getFormattedDecimalDuration(int $seconds): string
    {
        return $this->getFormatter()->durationDecimal($seconds);
    }

    public function getCurrencySymbol(string $currency): string
    {
        return $this->getFormatter()->currency($currency);
    }

    public function getLocale(): string
    {
        return $this->locale;
    }

    public function setLocale(string $locale): void
    {
        $this->locale = $locale;
        $this->formatter = null;
    }
}
