<?php

/*
 * This file is part of the Kimai time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Tests\Configuration;

use App\Configuration\SystemConfiguration;
use App\Configuration\ThemeConfiguration;
use PHPUnit\Framework\TestCase;

/**
 * @covers \App\Configuration\ThemeConfiguration
 * @covers \App\Configuration\SystemConfiguration
 */
class ThemeConfigurationTest extends TestCase
{
    protected function getSut(array $settings, array $loaderSettings = []): ThemeConfiguration
    {
        $loader = new TestConfigLoader($loaderSettings);
        $config = new SystemConfiguration($loader, ['theme' => $settings]);

        return new ThemeConfiguration($config);
    }

    /**
     * @return array
     */
    protected function getDefaultSettings()
    {
        return [
            'active_warning' => 3,
            'box_color' => 'green',
            'select_type' => null,
            'show_about' => true,
            'chart' => [
                'background_color' => 'rgba(0,115,183,0.7)',
                'border_color' => '#3b8bba',
                'grid_color' => 'rgba(0,0,0,.05)',
                'height' => '200'
            ],
            'branding' => [
                'logo' => null,
                'mini' => null,
                'company' => null,
                'title' => null,
            ],
            'tags_create' => true,
        ];
    }

    /**
     * @group legacy
     */
    public function testDeprecations()
    {
        $sut = $this->getSut($this->getDefaultSettings(), []);
        $this->assertTrue($sut->isAllowTagCreation());
        $this->assertNull($sut->getTitle());
    }
}
