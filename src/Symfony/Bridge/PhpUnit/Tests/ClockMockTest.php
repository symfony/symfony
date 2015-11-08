<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\PhpUnit\Tests;

use Symfony\Bridge\PhpUnit\ClockMock;

class ClockMockTest extends \PHPUnit_Framework_TestCase
{
    public function testTimeNowNull()
    {
        $this->assertTrue(is_int(ClockMock::time()));
    }

    public function testSleepNowNull()
    {
        $this->assertEquals(0, ClockMock::sleep(0));
    }

    public function testUsleepNowNull()
    {
        $this->assertNull(ClockMock::usleep(0));
    }

    public function testMicrotimeNowNull()
    {
        $this->assertTrue(is_float(ClockMock::microtime(true)));
    }

    public function testWithClockMock()
    {
        $this->assertFalse(ClockMock::withClockMock());
    }

    public function testTime()
    {
        ClockMock::withClockMock(true);

        $this->assertTrue(is_int(ClockMock::time()));
    }

    public function testSleep()
    {
        $this->assertEquals(0, ClockMock::sleep(0));
    }

    public function testUsleep()
    {
        $this->assertNull(ClockMock::usleep(0));
    }

    public function testMicrotimeTrue()
    {
        $this->assertTrue(is_float(ClockMock::microtime(true)));
    }

    public function testMicrotime()
    {
        $res = explode(" ", ClockMock::microtime());

        $this->assertTrue(is_float((float) $res[0]));
        $this->assertTrue(is_int((integer) $res[1]));
    }

    public function testWithClockMockTrue()
    {
        $this->assertNull(ClockMock::withClockMock(true));
    }

    public function testRegisterNormalClass()
    {
        $this->assertNull(ClockMock::register("Symfony\Bridge\PhpUnit\ClockMock"));
    }

    public function testRegisterTestsClass()
    {
        $this->assertNull(ClockMock::register("Symfony\Bridge\PhpUnit\Tests\ClockMockTest"));
    }
}
