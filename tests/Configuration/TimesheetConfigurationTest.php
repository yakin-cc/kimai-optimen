<?php

/*
 * This file is part of the Kimai time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Tests\Configuration;

use App\Configuration\SystemConfiguration;
use App\Configuration\TimesheetConfiguration;
use App\Entity\Configuration;
use PHPUnit\Framework\TestCase;

/**
 * @covers \App\Configuration\TimesheetConfiguration
 * @group legacy
 */
class TimesheetConfigurationTest extends TestCase
{
    /**
     * @param array $settings
     * @param array $loaderSettings
     * @return TimesheetConfiguration
     */
    protected function getSut(array $settings, array $loaderSettings = [])
    {
        $loader = new TestConfigLoader($loaderSettings);

        $config = new SystemConfiguration($loader, ['timesheet' => $settings]);

        return new TimesheetConfiguration($config);
    }

    protected function getDefaultSettings()
    {
        return [
            'rules' => [
                'allow_future_times' => false,
                'lockdown_period_start' => null,
                'lockdown_period_end' => null,
                'lockdown_grace_period' => null,
            ],
            'mode' => 'duration_only',
            'markdown_content' => false,
            'active_entries' => [
                'hard_limit' => 99,
            ],
            'default_begin' => 'now',
        ];
    }

    protected function getDefaultLoaderSettings()
    {
        return [
            (new Configuration())->setName('timesheet.rules.allow_future_times')->setValue('1'),
            (new Configuration())->setName('timesheet.rules.lockdown_period_start')->setValue('first day of last month'),
            (new Configuration())->setName('timesheet.rules.lockdown_period_end')->setValue('last day of last month'),
            (new Configuration())->setName('timesheet.rules.lockdown_grace_period')->setValue('+5 days'),
            (new Configuration())->setName('timesheet.mode')->setValue('default'),
            (new Configuration())->setName('timesheet.markdown_content')->setValue('1'),
            (new Configuration())->setName('timesheet.default_begin')->setValue('07:00'),
            (new Configuration())->setName('timesheet.active_entries.hard_limit')->setValue('7'),
        ];
    }

    public function testPrefix()
    {
        $sut = $this->getSut($this->getDefaultSettings(), []);
        $this->assertEquals('timesheet', $sut->getPrefix());
    }

    public function testDefaultWithoutLoader()
    {
        $sut = $this->getSut($this->getDefaultSettings(), []);

        $this->assertEquals(99, $sut->getActiveEntriesHardLimit());
        $this->assertEquals(99, $sut->getActiveEntriesSoftLimit());
        $this->assertFalse($sut->isAllowFutureTimes());
        $this->assertFalse($sut->isMarkdownEnabled());
        $this->assertEquals('duration_only', $sut->getTrackingMode());
        $this->assertEquals('now', $sut->getDefaultBeginTime());
        $this->assertFalse($sut->isLockdownActive());
        $this->assertEquals('', $sut->getLockdownPeriodStart());
        $this->assertEquals('', $sut->getLockdownPeriodEnd());
        $this->assertEquals('', $sut->getLockdownGracePeriod());
        $this->assertEquals('', $sut->isAllowOverlappingRecords());
        $this->assertEquals('', $sut->getDefaultRoundingDays());
        $this->assertEquals('', $sut->getDefaultRoundingMode());
        $this->assertEquals(0, $sut->getDefaultRoundingBegin());
        $this->assertEquals(0, $sut->getDefaultRoundingEnd());
        $this->assertEquals(0, $sut->getDefaultRoundingDuration());
    }

    public function testDefaultWithLoader()
    {
        $sut = $this->getSut($this->getDefaultSettings(), $this->getDefaultLoaderSettings());
        $this->assertEquals(7, $sut->getActiveEntriesHardLimit());
        $this->assertEquals(7, $sut->getActiveEntriesSoftLimit());
        $this->assertTrue($sut->isAllowFutureTimes());
        $this->assertTrue($sut->isMarkdownEnabled());
        $this->assertEquals('default', $sut->getTrackingMode());
        $this->assertEquals('07:00', $sut->getDefaultBeginTime());
        $this->assertTrue($sut->isLockdownActive());
        $this->assertEquals('first day of last month', $sut->getLockdownPeriodStart());
        $this->assertEquals('last day of last month', $sut->getLockdownPeriodEnd());
        $this->assertEquals('+5 days', $sut->getLockdownGracePeriod());
    }

    public function testDefaultWithMixedConfigs()
    {
        $sut = $this->getSut($this->getDefaultSettings(), [
            (new Configuration())->setName('timesheet.mode')->setValue('sdf'),
        ]);
        $this->assertEquals('sdf', $sut->getTrackingMode());
    }

    public function testFindByKey()
    {
        $sut = $this->getSut($this->getDefaultSettings(), []);
        $this->assertFalse($sut->find('rules.allow_future_times'));
        $this->assertFalse($sut->find('timesheet.rules.allow_future_times'));
    }

    public function testUnknownConfigAreImported()
    {
        $sut = $this->getSut($this->getDefaultSettings(), [
            (new Configuration())->setName('timesheet.foo')->setValue('hello'),
        ]);
        $this->assertEquals('hello', $sut->find('foo'));
    }
}
