<?php

/*
 * This file is part of the Kimai time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Tests\Widget\Type;

use App\Entity\Activity;
use App\Entity\Project;
use App\Entity\User;
use App\Model\Statistic\Day;
use App\Repository\TimesheetRepository;
use App\Widget\Type\AbstractWidgetType;
use App\Widget\Type\DailyWorkingTimeChart;
use App\Widget\Type\SimpleWidget;
use PHPUnit\Framework\TestCase;

/**
 * @covers \App\Widget\Type\DailyWorkingTimeChart
 * @covers \App\Widget\Type\SimpleWidget
 * @covers \App\Widget\Type\AbstractWidgetType
 * @covers \App\Repository\TimesheetRepository
 */
class DailyWorkingTimeChartTest extends TestCase
{
    public function createSut(): AbstractWidgetType
    {
        $repository = $this->createMock(TimesheetRepository::class);

        $sut = new DailyWorkingTimeChart($repository);
        $sut->setUser(new User());

        return $sut;
    }

    public function testExtendsSimpleWidget()
    {
        $sut = $this->createSut();
        self::assertInstanceOf(SimpleWidget::class, $sut);
    }

    public function testDefaultValues()
    {
        $sut = $this->createSut();
        self::assertInstanceOf(AbstractWidgetType::class, $sut);
        self::assertEquals('DailyWorkingTimeChart', $sut->getId());
        self::assertEquals('stats.yourWorkingHours', $sut->getTitle());
        self::assertNull($sut->getOption('begin', 'xxx'));
        self::assertNull($sut->getOption('end', 'xxx'));
        self::assertEquals('', $sut->getOption('color', 'xxx'));
        self::assertInstanceOf(User::class, $sut->getOption('user', 'xxx'));
        self::assertEquals('bar', $sut->getOption('type', 'xxx'));
    }

    public function testFluentInterface()
    {
        $sut = $this->createSut();
        self::assertInstanceOf(AbstractWidgetType::class, $sut->setOptions([]));
        self::assertInstanceOf(AbstractWidgetType::class, $sut->setId(''));
        self::assertInstanceOf(AbstractWidgetType::class, $sut->setTitle(''));
        self::assertInstanceOf(AbstractWidgetType::class, $sut->setData(''));
    }

    public function testSetter()
    {
        $sut = $this->createSut();

        // options
        $sut->setOption('föööö', 'trääääää');
        self::assertEquals('trääääää', $sut->getOption('föööö', 'tröööö'));

        // check default values
        self::assertEquals('xxxxx', $sut->getOption('blub', 'xxxxx'));
        self::assertEquals('xxxxx', $sut->getOption('dataType', 'xxxxx'));

        $sut->setOptions(['blub' => 'blab', 'dataType' => 'money']);
        // check option still exists
        self::assertEquals('trääääää', $sut->getOption('föööö', 'tröööö'));
        // check options are now existing
        self::assertEquals('blab', $sut->getOption('blub', 'xxxxx'));
        self::assertEquals('money', $sut->getOption('dataType', 'xxxxx'));

        // id
        $sut->setId('cvbnmyx');
        self::assertEquals('cvbnmyx', $sut->getId());
    }

    public function testGetOptions()
    {
        $sut = $this->createSut();

        $options = $sut->getOptions(['type' => 'xxx']);
        self::assertStringStartsWith('DailyWorkingTimeChart_', $options['id']);
        self::assertEquals('bar', $options['type']);
    }

    public function testGetData()
    {
        $activity = $this->createMock(Activity::class);
        $activity->method('getId')->willReturn(42);

        $project = $this->createMock(Project::class);
        $project->method('getId')->willReturn(4711);

        $repository = $this->getMockBuilder(TimesheetRepository::class)->disableOriginalConstructor()->onlyMethods(['getDailyData'])->getMock();
        $repository->expects($this->once())->method('getDailyData')->willReturnCallback(function ($begin, $end, $user) use ($activity, $project) {
            return [
                [
                    'year' => $begin->format('Y'),
                    'month' => $begin->format('n'),
                    'day' => $begin->format('j'),
                    'rate' => 13.75,
                    'duration' => 1234,
                    'billable' => 1234,
                    'details' => [
                        [
                            'activity' => $activity,
                            'project' => $project,
                            'billable' => 1234,
                        ]
                    ]
                ]
            ];
        });

        $sut = new DailyWorkingTimeChart($repository);
        $sut->setUser(new User());
        $data = $sut->getData([]);
        self::assertCount(2, $data);
        self::assertArrayHasKey('activities', $data);
        self::assertArrayHasKey('data', $data);

        self::assertCount(1, $data['activities']);
        self::assertArrayHasKey('4711_42', $data['activities']);
        self::assertCount(2, $data['activities']['4711_42']);
        self::assertArrayHasKey('activity', $data['activities']['4711_42']);
        self::assertArrayHasKey('project', $data['activities']['4711_42']);
        self::assertSame($activity, $data['activities']['4711_42']['activity']);
        self::assertSame($project, $data['activities']['4711_42']['project']);

        self::assertCount(7, $data['data']);
        foreach ($data['data'] as $statObj) {
            self::assertInstanceOf(Day::class, $statObj);
        }
    }
}
