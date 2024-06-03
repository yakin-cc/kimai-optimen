<?php

/*
 * This file is part of the Kimai time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Tests\Event;

use App\Entity\User;
use App\Event\ThemeEvent;
use PHPUnit\Framework\TestCase;

/**
 * @covers \App\Event\ThemeEvent
 */
class ThemeEventTest extends TestCase
{
    public function testEmpty()
    {
        $sut = new ThemeEvent();
        $this->assertNull($sut->getUser());
        $this->assertNull($sut->getPayload());
        $this->assertEquals('', $sut->getContent());
    }

    public function testDefaultValues()
    {
        $user = new User();
        $user->setAlias('foo');

        $sut = new ThemeEvent($user);

        $this->assertSame($user, $sut->getUser());
    }

    public function testGetterAndSetter()
    {
        $user = new User();
        $user->setAlias('foo');

        $payload = [null, '', 'test', new \stdClass()];

        $sut = new ThemeEvent($user);
        $sut->setPayload($payload);
        $this->assertEquals($payload, $sut->getPayload());

        $sut = new ThemeEvent($user, $payload);
        $this->assertEquals($payload, $sut->getPayload());

        $sut = new ThemeEvent($user);
        $sut->addContent('foo');
        $this->assertEquals('foo', $sut->getContent());

        $sut->addContent('<script>');
        $this->assertEquals('foo<script>', $sut->getContent());
    }
}
