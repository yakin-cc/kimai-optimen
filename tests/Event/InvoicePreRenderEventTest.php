<?php

/*
 * This file is part of the Kimai time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Tests\Event;

use App\Entity\InvoiceDocument;
use App\Event\InvoicePreRenderEvent;
use App\Tests\Invoice\DebugFormatter;
use App\Tests\Invoice\Renderer\DebugRenderer;
use App\Tests\Mocks\InvoiceModelFactoryFactory;
use PHPUnit\Framework\TestCase;

/**
 * @covers \App\Event\InvoicePreRenderEvent
 */
class InvoicePreRenderEventTest extends TestCase
{
    public function testDefaultValues()
    {
        $model = (new InvoiceModelFactoryFactory($this))->create()->createModel(new DebugFormatter());
        $document = new InvoiceDocument(new \SplFileInfo(__FILE__));
        $renderer = new DebugRenderer();

        $sut = new InvoicePreRenderEvent($model, $document, $renderer);

        self::assertSame($model, $sut->getModel());
        self::assertSame($document, $sut->getDocument());
        self::assertSame($renderer, $sut->getRenderer());
    }
}
