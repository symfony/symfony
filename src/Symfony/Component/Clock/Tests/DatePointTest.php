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

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestWith;
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

    #[DataProvider('provideDatetimeStrings')]
    public function testNativeClockGivesSameResultAsReferenceDate(string $datetime, ?string $timezone)
    {
        Clock::set(new NativeClock('Europe/Madrid'));
        $timezone = null === $timezone ? null : new \DateTimeZone($timezone);

        $reference = new \DateTimeImmutable('now', new \DateTimeZone('Europe/Madrid'));
        $expected = new DatePoint($datetime, $timezone, $reference);
        $date = new DatePoint($datetime, $timezone);

        $this->assertSame($expected->getTimezone()->getName(), $date->getTimezone()->getName());
        $this->assertSame('000000' === $expected->format('u'), '000000' === $date->format('u'));
        $this->assertEqualsWithDelta($expected->getTimestamp(), $date->getTimestamp(), 1);
    }

    public static function provideDatetimeStrings(): iterable
    {
        foreach ([null, 'America/New_York'] as $timezone) {
            yield ['now', $timezone];
            yield ['+1 day', $timezone];
            yield ['+90 minutes', $timezone];
            yield ['2024-01-01', $timezone];
            yield ['2024-01-01 10:00', $timezone];
            yield ['2024-03-31 02:30', $timezone];
            yield ['10:00', $timezone];
            yield ['tomorrow', $timezone];
            yield ['midnight', $timezone];
            yield ['noon', $timezone];
            yield ['yesterday 14:00', $timezone];
            yield ['first day of next month', $timezone];
            yield ['last monday', $timezone];
            yield ['2024-01-01 10:00 Europe/Paris', $timezone];
            yield ['now Asia/Tokyo', $timezone];
            yield ['+1 day Asia/Tokyo', $timezone];
            yield ['10:00 Asia/Tokyo', $timezone];
            yield ['+1 hour +05:00', $timezone];
            yield ['2024-01-01T10:00:00+05:00', $timezone];
            yield ['@1700000000', $timezone];
        }
    }

    public function testEmptyStringIsRejectedWithNativeClock()
    {
        Clock::set(new NativeClock());

        $this->expectException(\DateMalformedStringException::class);

        new DatePoint('');
    }

    public function testUsesTimezoneOfGlobalNativeClock()
    {
        Clock::set(new NativeClock('Asia/Tokyo'));

        $this->assertSame('Asia/Tokyo', (new DatePoint())->getTimezone()->getName());
        $this->assertSame('Asia/Tokyo', (new DatePoint('+1 day'))->getTimezone()->getName());
        $this->assertSame('UTC', (new DatePoint('now', new \DateTimeZone('UTC')))->getTimezone()->getName());
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

    public function testCreateFromFormat()
    {
        $date = DatePoint::createFromFormat('Y-m-d H:i:s', '2010-01-28 15:00:00');

        $this->assertInstanceOf(DatePoint::class, $date);
        $this->assertSame('2010-01-28 15:00:00', $date->format('Y-m-d H:i:s'));

        $this->expectException(\DateMalformedStringException::class);
        $this->expectExceptionMessage('A four digit year could not be found');
        DatePoint::createFromFormat('Y-m-d H:i:s', 'Bad Date');
    }

    #[DataProvider('provideValidTimestamps')]
    public function testCreateFromTimestamp(int|float $timestamp, string $expected)
    {
        $date = DatePoint::createFromTimestamp($timestamp);

        $this->assertInstanceOf(DatePoint::class, $date);
        $this->assertSame($expected, $date->format('Y-m-d\TH:i:s.uP'));
    }

    public static function provideValidTimestamps(): iterable
    {
        yield 'positive integer' => [1359188516, '2013-01-26T08:21:56.000000+00:00'];
        yield 'positive float' => [1359188516.123456, '2013-01-26T08:21:56.123456+00:00'];
        yield 'positive integer-ish float' => [1359188516.0, '2013-01-26T08:21:56.000000+00:00'];
        yield 'zero as integer' => [0, '1970-01-01T00:00:00.000000+00:00'];
        yield 'zero as float' => [0.0, '1970-01-01T00:00:00.000000+00:00'];
        yield 'negative integer' => [-100, '1969-12-31T23:58:20.000000+00:00'];
        yield 'negative float' => [-100.123456, '1969-12-31T23:58:19.876544+00:00'];
        yield 'negative integer-ish float' => [-100.0, '1969-12-31T23:58:20.000000+00:00'];
    }

    #[DataProvider('provideOutOfRangeFloatTimestamps')]
    public function testCreateFromTimestampWithFloatOutOfRange(float $timestamp)
    {
        $this->expectException(\DateRangeError::class);
        $this->expectExceptionMessage('DateTimeImmutable::createFromTimestamp(): Argument #1 ($timestamp) must be a finite number between');
        DatePoint::createFromTimestamp($timestamp);
    }

    public static function provideOutOfRangeFloatTimestamps(): iterable
    {
        yield 'too large (positive)' => [1e20];
        yield 'too large (negative)' => [-1e20];
        yield 'NaN' => [\NAN];
        yield 'infinity' => [\INF];
    }

    public function testModify()
    {
        $date = new DatePoint('2010-01-28 15:00:00');
        $date = $date->modify('+1 day');

        $this->assertInstanceOf(DatePoint::class, $date);
        $this->assertSame('2010-01-29 15:00:00', $date->format('Y-m-d H:i:s'));

        $this->expectException(\DateMalformedStringException::class);
        $this->expectExceptionMessage('Failed to parse time string (Bad Date)');
        $_ = $date->modify('Bad Date');
    }

    public function testMicrosecond()
    {
        $date = new DatePoint('2010-01-28 15:00:00.123456');

        $this->assertSame('2010-01-28 15:00:00.123456', $date->format('Y-m-d H:i:s.u'));

        $date = $date->setMicrosecond(789);

        $this->assertSame('2010-01-28 15:00:00.000789', $date->format('Y-m-d H:i:s.u'));
        $this->assertSame(789, $date->getMicrosecond());

        $this->expectException(\DateRangeError::class);
        $this->expectExceptionMessage('DatePoint::setMicrosecond(): Argument #1 ($microsecond) must be between 0 and 999999, 1000000 given');
        $_ = $date->setMicrosecond(1000000);
    }

    public function testNoDiscard()
    {
        $methods = [];

        foreach ((new \ReflectionClass(DatePoint::class))->getMethods() as $method) {
            if ($method->getAttributes('NoDiscard')) {
                $methods[] = $method->name;
            }
        }

        sort($methods);

        $this->assertSame(['add', 'modify', 'setDate', 'setISODate', 'setMicrosecond', 'setTime', 'setTimestamp', 'setTimezone', 'sub'], $methods);
    }

    #[TestWith(['2024-04-01 00:00:00.000000', '2024-04'])]
    #[TestWith(['2024-04-09 00:00:00.000000', '2024-04-09'])]
    #[TestWith(['2024-04-09 03:00:00.000000', '2024-04-09 03:00'])]
    #[TestWith(['2024-04-09 00:00:00.123456', '2024-04-09 00:00:00.123456'])]
    public function testTimeDefaultsToMidnight(string $expected, string $datetime)
    {
        $date = new \DateTimeImmutable($datetime);
        $this->assertSame($expected, $date->format('Y-m-d H:i:s.u'));

        $date = new DatePoint($datetime);
        $this->assertSame($expected, $date->format('Y-m-d H:i:s.u'));
    }
}
