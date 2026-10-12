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
use Symfony\Component\Clock\Clock;
use Symfony\Component\Clock\DatePoint;
use Symfony\Component\Clock\NativeClock;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;

class DatePointTest extends TestCase
{
    use ClockSensitiveTrait;

    public function testDatePoint()
    {
        self::mockTime('2010-01-28 15:00:00 UTC');

        $date = new DatePoint();
        $this->assertSame('2010-01-28 15:00:00 UTC', $date->format('Y-m-d H:i:s e'));

        $date = new DatePoint('+1 day Europe/Paris');
        $this->assertSame('2010-01-29 16:00:00 Europe/Paris', $date->format('Y-m-d H:i:s e'));

        $date = new DatePoint('2022-01-28 15:00:00 Europe/Paris');
        $this->assertSame('2022-01-28 15:00:00 Europe/Paris', $date->format('Y-m-d H:i:s e'));
    }

    public function testNowWithNativeClock()
    {
        Clock::set(new NativeClock('Asia/Tokyo'));

        $before = new \DateTimeImmutable();
        $date = new DatePoint();
        $after = new \DateTimeImmutable();

        $this->assertSame('Asia/Tokyo', $date->getTimezone()->getName());
        $this->assertGreaterThanOrEqual($before, $date);
        $this->assertLessThanOrEqual($after, $date);

        $this->assertSame('UTC', (new DatePoint('now', new \DateTimeZone('UTC')))->getTimezone()->getName());
    }

    /**
     * @requires PHP 8.1.7
     *
     * @testWith ["2016-10-30 02:30:00+02:00", "now", "2016-10-30 00:30:00 UTC"]
     *           ["2016-10-30 02:30:01+02:00", "+1 second", "2016-10-30 00:30:00 UTC"]
     *           ["2016-10-30 02:30:00+01:00", "now", "2016-10-30 01:30:00 UTC"]
     *           ["2016-10-30 02:30:01+01:00", "+1 second", "2016-10-30 01:30:00 UTC"]
     *           ["2016-10-30 02:30:00+01:00", "2016-10-30 02:30", "2016-07-14 12:00:00 UTC"]
     *           ["2016-10-30 02:30:00+02:00", "2016-10-30 02:30 Europe/Paris", "2016-01-14 12:00:00 UTC"]
     */
    public function testFallBackHourWithReference(string $expected, string $datetime, string $reference)
    {
        $timezone = new \DateTimeZone('Europe/Paris');

        $date = new DatePoint($datetime, $timezone, new \DateTimeImmutable($reference));
        $this->assertSame($expected, $date->format('Y-m-d H:i:sP'));

        self::mockTime(new \DateTimeImmutable($reference));

        $date = new DatePoint($datetime, $timezone);
        $this->assertSame($expected, $date->format('Y-m-d H:i:sP'));
    }

    public function testCreateFromFormat()
    {
        $date = DatePoint::createFromFormat('Y-m-d H:i:s', '2010-01-28 15:00:00');

        $this->assertInstanceOf(DatePoint::class, $date);
        $this->assertSame('2010-01-28 15:00:00', $date->format('Y-m-d H:i:s'));

        $this->expectException(\DateMalformedStringException::class);
        $this->expectExceptionMessage('A four digit year could not be found');
        DatePoint::createFromFormat('Y-m-d H:i:s', 'Bad Date');
    }

    public function testModify()
    {
        $date = new DatePoint('2010-01-28 15:00:00');
        $date = $date->modify('+1 day');

        $this->assertInstanceOf(DatePoint::class, $date);
        $this->assertSame('2010-01-29 15:00:00', $date->format('Y-m-d H:i:s'));

        $this->expectException(\DateMalformedStringException::class);
        $this->expectExceptionMessage('Failed to parse time string (Bad Date)');
        $date->modify('Bad Date');
    }

    /**
     * @testWith ["2024-04-01 00:00:00.000000", "2024-04"]
     *           ["2024-04-09 00:00:00.000000", "2024-04-09"]
     *           ["2024-04-09 03:00:00.000000", "2024-04-09 03:00"]
     *           ["2024-04-09 00:00:00.123456", "2024-04-09 00:00:00.123456"]
     */
    public function testTimeDefaultsToMidnight(string $expected, string $datetime)
    {
        $date = new \DateTimeImmutable($datetime);
        $this->assertSame($expected, $date->format('Y-m-d H:i:s.u'));

        $date = new DatePoint($datetime);
        $this->assertSame($expected, $date->format('Y-m-d H:i:s.u'));
    }

    /**
     * @testWith ["2016-09-08 01:00:00.000000", "2016-09-08 +1 hour"]
     *           ["2016-09-07 23:00:00.000000", "2016-09-08 -1 hour"]
     *           ["2016-09-09 12:00:00.000000", "2016-09-08 +36 hours"]
     *           ["2016-09-08 01:00:00.000000", "+1 hour 2016-09-08"]
     *           ["2016-09-01 01:00:00.000000", "2016-09 +1 hour"]
     *           ["2018-11-04 01:00:00.000000", "2018-11-04", "America/Sao_Paulo"]
     *           ["2016-09-08 10:30:00.000000", "2016-09-08T1030"]
     *           ["2016-09-09 10:30:00.000000", "2016-09-08 t1030 +1 day"]
     */
    public function testTimeDefaultsToMidnightWithReference(string $expected, string $datetime, string $timezone = 'UTC')
    {
        $timezone = new \DateTimeZone($timezone);

        $date = new \DateTimeImmutable($datetime, $timezone);
        $this->assertSame($expected, $date->format('Y-m-d H:i:s.u'));

        $date = new DatePoint($datetime, $timezone, new \DateTimeImmutable('2024-01-10 22:20:02.654321', $timezone));
        $this->assertSame($expected, $date->format('Y-m-d H:i:s.u'));
    }

    /**
     * @testWith ["3000-01-01 00:00:00.000000", "3000-01-01"]
     *           ["1800-01-01 00:00:00.000000", "1800-01-01"]
     *           ["3000-01-01 10:00:00.000000", "10:00", "3000-01-01 05:06:07.123456"]
     *           ["3000-01-01 10:30:00.000000", "t1030", "3000-01-01 05:06:07.123456"]
     *           ["1800-01-01 10:30:00.000000", "t1030", "1800-01-01 05:06:07.123456"]
     *           ["3000-01-02 05:06:07.123456", "+1 day", "3000-01-01 05:06:07.123456"]
     */
    public function testDatesOutsideThe32BitTimestampRange(string $expected, string $datetime, ?string $reference = null)
    {
        $timezone = new \DateTimeZone('UTC');
        $date = new DatePoint($datetime, $timezone, null !== $reference ? new \DateTimeImmutable($reference, $timezone) : null);

        $this->assertSame($expected, $date->format('Y-m-d H:i:s.u'));
    }
}
