<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Clock\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

class MockClockTest extends TestCase
{
    public function testConstruct()
    {
        $clock = new MockClock();
        $this->assertSame('UTC', $clock->now()->getTimezone()->getName());

        $tz = 'Europe/Paris';
        $clock = new MockClock($tz);
        $this->assertSame($tz, $clock->now()->getTimezone()->getName());

        $clock = new MockClock('now', $tz);
        $this->assertSame($tz, $clock->now()->getTimezone()->getName());

        $clock = new MockClock('now', new \DateTimeZone($tz));
        $this->assertSame($tz, $clock->now()->getTimezone()->getName());

        $now = new \DateTimeImmutable();
        $clock = new MockClock($now);
        $this->assertEquals($now, $clock->now());

        $clock = new MockClock('2022-06-20 00:00:00');
        $this->assertSame('2022-06-20 00:00:00', $clock->now()->format('Y-m-d H:i:s'));
    }

    public function testNow()
    {
        $before = new \DateTimeImmutable();
        $clock = new MockClock();
        usleep(1);
        $after = new \DateTimeImmutable();

        $this->assertGreaterThan($before, $clock->now());
        $this->assertLessThan($after, $clock->now());

        $this->assertEquals($clock->now(), $clock->now());
        $this->assertNotSame($clock->now(), $clock->now());
    }

    public function testSleep()
    {
        $clock = new MockClock((new \DateTimeImmutable('2112-09-17 23:53:00.999Z'))->setTimezone(new \DateTimeZone('UTC')));
        $tz = $clock->now()->getTimezone()->getName();

        $clock->sleep(2.002001);
        $this->assertSame('2112-09-17 23:53:03.001001', $clock->now()->format('Y-m-d H:i:s.u'));
        $this->assertSame($tz, $clock->now()->getTimezone()->getName());
    }

    public static function provideValidModifyStrings(): iterable
    {
        yield 'absolute datetime value' => [
            '2112-09-17 23:53:03.001',
            '2112-09-17 23:53:03.001000',
        ];

        yield 'relative modified date' => [
            '+2 days',
            '2112-09-19 23:53:00.999000',
        ];
    }

    /**
     * @dataProvider provideValidModifyStrings
     */
    public function testModifyWithSpecificDateTime(string $modifiedNow, string $expectedNow)
    {
        $clock = new MockClock((new \DateTimeImmutable('2112-09-17 23:53:00.999Z'))->setTimezone(new \DateTimeZone('UTC')));
        $tz = $clock->now()->getTimezone()->getName();

        $clock->modify($modifiedNow);

        $this->assertSame($expectedNow, $clock->now()->format('Y-m-d H:i:s.u'));
        $this->assertSame($tz, $clock->now()->getTimezone()->getName());
    }

    public static function provideInvalidModifyStrings(): iterable
    {
        yield 'Named holiday is not recognized' => [
            'Halloween',
            'Invalid modifier: "Halloween". Could not modify MockClock.',
        ];

        yield 'empty string' => [
            '',
            'Invalid modifier: "". Could not modify MockClock.',
        ];
    }

    /**
     * @dataProvider provideInvalidModifyStrings
     */
    public function testModifyThrowsOnInvalidString(string $modifiedNow, string $expectedMessage)
    {
        $clock = new MockClock((new \DateTimeImmutable('2112-09-17 23:53:00.999Z'))->setTimezone(new \DateTimeZone('UTC')));

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage($expectedMessage);

        $clock->modify($modifiedNow);
    }

    public function testWithTimeZone()
    {
        $clock = new MockClock();
        $utcClock = $clock->withTimeZone('UTC');

        $this->assertNotSame($clock, $utcClock);
        $this->assertSame('UTC', $utcClock->now()->getTimezone()->getName());
    }
}
