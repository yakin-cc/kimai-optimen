<?php

/*
 * This file is part of the Kimai time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Invoice\Calculator;

use App\Invoice\CalculatorInterface;
use App\Invoice\InvoiceItem;
use App\Invoice\InvoiceModel;

abstract class AbstractCalculator
{
    /**
     * @var string
     */
    protected $currency;

    /**
     * @var InvoiceModel
     */
    protected $model;

    /**
     * @return InvoiceItem[]
     */
    abstract public function getEntries();

    /**
     * @return string
     */
    abstract public function getId(): string;

    /**
     * @param InvoiceModel $model
     */
    public function setModel(InvoiceModel $model)
    {
        $this->model = $model;
    }

    /**
     * @return float
     */
    public function getSubtotal(): float
    {
        $amount = 0.00;
        foreach ($this->model->getEntries() as $entry) {
            $amount += $entry->getRate();
        }

        return round($amount, 2);
    }

    /**
     * @return float
     */
    public function getVat(): ?float
    {
        return $this->model->getTemplate()->getVat();
    }

    /**
     * @return float
     */
    public function getTax(): float
    {
        $vat = $this->getVat();
        if (0.00 === $vat) {
            return 0.00;
        }

        $percent = $vat / 100.00;

        return round($this->getSubtotal() * $percent, 2);
    }

    /**
     * @return float
     */
    public function getTotal(): float
    {
        return $this->getSubtotal() + $this->getTax();
    }

    /**
     * @deprecated since 1.8 will be removed with 2.0
     * @return string
     */
    public function getCurrency(): string
    {
        @trigger_error(
            sprintf('%s::getCurrency() is deprecated and will be removed with 2.0', CalculatorInterface::class),
            E_USER_DEPRECATED
        );

        return $this->model->getCurrency();
    }

    /**
     * Returns the total amount of worked time in seconds.
     *
     * @return int
     */
    public function getTimeWorked(): int
    {
        $time = 0;
        foreach ($this->model->getEntries() as $entry) {
            if (null !== $entry->getDuration()) {
                $time += $entry->getDuration();
            }
        }

        return $time;
    }
}
