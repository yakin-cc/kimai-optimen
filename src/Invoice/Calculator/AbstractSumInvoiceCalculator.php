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
use App\Invoice\InvoiceItemInterface;

/**
 * An abstract calculator that sums up the invoice item records.
 */
abstract class AbstractSumInvoiceCalculator extends AbstractMergedCalculator implements CalculatorInterface
{
    abstract protected function calculateSumIdentifier(InvoiceItemInterface $invoiceItem): string;

    protected function calculateIdentifier(InvoiceItemInterface $entry): string
    {
        $prefix = $this->calculateSumIdentifier($entry);

        if (null !== $entry->getFixedRate()) {
            return $prefix . '_fixed_' . (string) $entry->getFixedRate();
        }

        return $prefix . '_hourly_' . (string) $entry->getHourlyRate();
    }

    /**
     * @return InvoiceItem[]
     */
    public function getEntries()
    {
        $entries = $this->model->getEntries();
        if (empty($entries)) {
            return [];
        }

        /** @var InvoiceItem[] $invoiceItems */
        $invoiceItems = [];

        foreach ($entries as $entry) {
            $id = $this->calculateIdentifier($entry);

            if (!isset($invoiceItems[$id])) {
                $invoiceItems[$id] = new InvoiceItem();
            }
            $invoiceItem = $invoiceItems[$id];
            $this->mergeInvoiceItems($invoiceItem, $entry);
            $this->mergeSumInvoiceItem($invoiceItem, $entry);
        }

        return array_values($invoiceItems);
    }

    /**
     * @param InvoiceItem $invoiceItem
     * @param InvoiceItemInterface $entry
     * @return void
     */
    protected function mergeSumInvoiceItem(InvoiceItem $invoiceItem, InvoiceItemInterface $entry) /* : void */
    {
        if (method_exists($this, 'mergeSumTimesheet')) {
            @trigger_error('mergeSumTimesheet() is deprecated and will be removed with 2.0 - use mergeSumInvoiceItem() instead', E_USER_DEPRECATED);
            $this->mergeSumTimesheet($invoiceItem, $entry);
        }
    }
}
