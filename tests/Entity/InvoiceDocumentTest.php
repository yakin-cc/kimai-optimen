<?php

/*
 * This file is part of the Kimai time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Tests\Entity;

use App\Entity\InvoiceDocument;
use PHPUnit\Framework\TestCase;

/**
 * @covers \App\Entity\InvoiceDocument
 */
class InvoiceDocumentTest extends TestCase
{
    public function testDefaultValues()
    {
        $dir = realpath(__DIR__ . '/../../templates/invoice/renderer');
        $sut = new InvoiceDocument(new \SplFileInfo($dir . '/default.html.twig'));

        self::assertEquals('twig', $sut->getFileExtension());
        self::assertStringContainsString('templates/invoice/renderer/default.html.twig', $sut->getFilename());
        self::assertEquals('default', $sut->getId());
        self::assertEquals('default.html.twig', $sut->getName());
        self::assertIsInt($sut->getLastChange());
    }

    public function testThrowsOnDeletedFile()
    {
        $catchedException = false;

        $dir = realpath(__DIR__ . '/../../templates/invoice/renderer');
        $file = tempnam($dir, 'invoice-renderer');

        if ($file !== false) {
            touch($file);
            $sut = new InvoiceDocument(new \SplFileInfo($file));
            unlink($file);

            try {
                // names are cached by SplFileInfo, so we need to trigger a function that does access the file
                $sut->getLastChange();
            } catch (\Exception $exception) {
                $catchedException = true;
            }
        }

        self::assertTrue($catchedException, 'Invoice document did not throw exception');
    }
}
