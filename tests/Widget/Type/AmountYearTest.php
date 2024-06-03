<?php

/*
 * This file is part of the Kimai time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Tests\Widget\Type;

use App\Configuration\SystemConfiguration;
use App\Repository\TimesheetRepository;
use App\Widget\Type\AbstractWidgetType;
use App\Widget\Type\AmountYear;
use App\Widget\Type\CounterYear;
use App\Widget\Type\SimpleStatisticChart;
use App\Widget\WidgetInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @covers \App\Widget\Type\AmountYear
 * @covers \App\Widget\Type\CounterYear
 */
class AmountYearTest extends AbstractWidgetTypeTest
{
    protected function assertDefaultData(AbstractWidgetType $sut)
    {
        self::assertEquals(0.0, $sut->getData());
    }

    /**
     * @return CounterYear
     */
    public function createSut(): AbstractWidgetType
    {
        $repository = $this->createMock(TimesheetRepository::class);
        $configuration = $this->createMock(SystemConfiguration::class);
        $dispatcher = $this->createMock(EventDispatcherInterface::class);

        return new AmountYear($repository, $configuration, $dispatcher);
    }

    public function getDefaultOptions(): array
    {
        return [
            'dataType' => 'money',
            'icon' => 'money',
            'color' => WidgetInterface::COLOR_YEAR,
        ];
    }

    public function testData()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot set data on instances of SimpleStatisticChart');

        $sut = $this->createSut();
        self::assertInstanceOf(SimpleStatisticChart::class, $sut);
        $sut->setData(10);
    }

    public function testSettings()
    {
        $sut = $this->createSut();

        self::assertEquals('widget/widget-counter.html.twig', $sut->getTemplateName());
        self::assertEquals('amountYear', $sut->getId());
    }
}
