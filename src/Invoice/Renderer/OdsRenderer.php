<?php

/*
 * This file is part of the Kimai time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Invoice\Renderer;

use App\Invoice\RendererInterface;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

final class OdsRenderer extends AbstractSpreadsheetRenderer implements RendererInterface
{
    /**
     * @return string[]
     */
    protected function getFileExtensions()
    {
        return ['.ods'];
    }

    /**
     * @return string
     */
    protected function getContentType()
    {
        return 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
    }

    /**
     * @param Spreadsheet $spreadsheet
     * @return string
     * @throws \Exception
     */
    protected function saveSpreadsheet(Spreadsheet $spreadsheet)
    {
        $filename = @tempnam(sys_get_temp_dir(), 'kimai-invoice-ods');
        if (false === $filename) {
            throw new \Exception('Could not open temporary file');
        }

        $writer = IOFactory::createWriter($spreadsheet, 'Ods');
        $writer->save($filename);

        return $filename;
    }
}
