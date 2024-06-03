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
 * A calculator that sums up the invoice item records by activity.
 */
class ActivityInvoiceCalculator extends AbstractSumInvoiceCalculator implements CalculatorInterface
{
    protected function calculateSumIdentifier(InvoiceItemInterface $invoiceItem): string
    {
        if (null === $invoiceItem->getActivity()) {
            return '__NULL__';
        }

        return (string) $invoiceItem->getActivity()->getId();
    }

    protected function mergeSumInvoiceItem(InvoiceItem $invoiceItem, InvoiceItemInterface $entry)
    {
        if (null === $entry->getActivity()) {
            return;
        }

        if ($entry->getActivity()->getInvoiceText() !== null) {
            $invoiceItem->setDescription($entry->getActivity()->getInvoiceText());
        } else {
            $invoiceItem->setDescription($entry->getActivity()->getName());
        }
    }

    /**
     * @return string
     */
    public function getId(): string
    {
        return 'activity';
    }
}
