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

use PHPUnit\Framework\TestCase;
use Symfony\Bridge\PhpUnit\ClockMock;

/**
 * @author Dominic Tubach <dominic.tubach@to.com>
 *
 * @covers \Symfony\Bridge\PhpUnit\ClockMock
 */
class ClockMockTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        ClockMock::register(__CLASS__);
    }

    protected function setUp(): void
    {
        ClockMock::withClockMock(1234567890.125);
    }

    public function testTime()
    {
        self::assertSame(1234567890, time());
    }

    public function testSleep()
    {
        sleep(2);
        self::assertSame(1234567892, time());
    }

    public function testMicrotime()
    {
        self::assertSame('0.12500000 1234567890', microtime());
    }

    public function testMicrotimeAsFloat()
    {
        self::assertSame(1234567890.125, microtime(true));
    }

    public function testUsleep()
    {
        usleep(2);
        self::assertSame(1234567890.125002, microtime(true));
    }

    public function testDate()
    {
        self::assertSame('1234567890', date('U'));
    }

    public function testGmDate()
    {
        ClockMock::withClockMock(1555075769);

        self::assertSame('1555075769', gmdate('U'));
    }
}
