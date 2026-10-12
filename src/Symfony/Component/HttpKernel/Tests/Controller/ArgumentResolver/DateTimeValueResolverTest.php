<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\Tests\Controller\ArgumentResolver;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\MapDateTime;
use Symfony\Component\HttpKernel\Controller\ArgumentResolver\DateTimeValueResolver;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class DateTimeValueResolverTest extends TestCase
{
    private readonly string $defaultTimezone;

    protected function setUp(): void
    {
        $this->defaultTimezone = date_default_timezone_get();
    }

    protected function tearDown(): void
    {
        date_default_timezone_set($this->defaultTimezone);
    }

    public static function getTimeZones()
    {
        yield ['UTC', false];
        yield ['Pacific/Honolulu', false];
        yield ['America/Toronto', false];
        yield ['UTC', true];
        yield ['Pacific/Honolulu', true];
        yield ['America/Toronto', true];
    }

    public static function getClasses()
    {
        yield [\DateTimeInterface::class];
        yield [\DateTime::class];
        yield [FooDateTime::class];
    }

    /**
     * @group legacy
     */
    public function testSupports()
    {
        $resolver = new DateTimeValueResolver();

        $argument = new ArgumentMetadata('dummy', \DateTime::class, false, false, null);
        $request = self::requestWithAttributes(['dummy' => 'now']);
        $this->assertTrue($resolver->supports($request, $argument));

        $argument = new ArgumentMetadata('dummy', FooDateTime::class, false, false, null);
        $request = self::requestWithAttributes(['dummy' => 'now']);
        $this->assertTrue($resolver->supports($request, $argument));

        $argument = new ArgumentMetadata('dummy', \stdClass::class, false, false, null);
        $request = self::requestWithAttributes(['dummy' => 'now']);
        $this->assertFalse($resolver->supports($request, $argument));
    }

    public function testUnsupportedArgument()
    {
        $resolver = new DateTimeValueResolver();

        $argument = new ArgumentMetadata('dummy', \stdClass::class, false, false, null);
        $request = self::requestWithAttributes(['dummy' => 'now']);
        $this->assertSame([], $resolver->resolve($request, $argument));
    }

    /**
     * @dataProvider getTimeZones
     */
    public function testFullDate(string $timezone, bool $withClock)
    {
        date_default_timezone_set($withClock ? 'UTC' : $timezone);
        $resolver = new DateTimeValueResolver($withClock ? new MockClock('now', $timezone) : null);

        $argument = new ArgumentMetadata('dummy', \DateTimeImmutable::class, false, false, null);
        $request = self::requestWithAttributes(['dummy' => '2012-07-21 00:00:00']);

        $results = $resolver->resolve($request, $argument);

        $this->assertCount(1, $results);
        $this->assertInstanceOf(\DateTimeImmutable::class, $results[0]);
        $this->assertSame($timezone, $results[0]->getTimezone()->getName(), 'Default timezone');
        $this->assertEquals('2012-07-21 00:00:00', $results[0]->format('Y-m-d H:i:s'));
    }

    /**
     * @dataProvider getTimeZones
     */
    public function testUnixTimestamp(string $timezone, bool $withClock)
    {
        date_default_timezone_set($withClock ? 'UTC' : $timezone);
        $resolver = new DateTimeValueResolver($withClock ? new MockClock('now', $timezone) : null);

        $argument = new ArgumentMetadata('dummy', \DateTimeImmutable::class, false, false, null);
        $request = self::requestWithAttributes(['dummy' => '989541720']);

        $results = $resolver->resolve($request, $argument);

        $this->assertCount(1, $results);
        $this->assertInstanceOf(\DateTimeImmutable::class, $results[0]);
        $this->assertSame('+00:00', $results[0]->getTimezone()->getName(), 'Timestamps are UTC');
        $this->assertEquals('2001-05-11 00:42:00', $results[0]->format('Y-m-d H:i:s'));
    }

    public function testNullableWithEmptyAttribute()
    {
        $resolver = new DateTimeValueResolver();

        $argument = new ArgumentMetadata('dummy', \DateTimeImmutable::class, false, false, null, true);
        $request = self::requestWithAttributes(['dummy' => '']);

        $results = $resolver->resolve($request, $argument);

        $this->assertCount(1, $results);
        $this->assertNull($results[0]);
    }

    /**
     * @param class-string<\DateTimeInterface> $class
     *
     * @dataProvider getClasses
     */
    public function testNow(string $class)
    {
        date_default_timezone_set($timezone = 'Pacific/Honolulu');
        $resolver = new DateTimeValueResolver();

        $argument = new ArgumentMetadata('dummy', $class, false, false, null, false);
        $request = self::requestWithAttributes(['dummy' => null]);

        $results = $resolver->resolve($request, $argument);

        $this->assertCount(1, $results);
        $this->assertInstanceOf($class, $results[0]);
        $this->assertSame($timezone, $results[0]->getTimezone()->getName(), 'Default timezone');
        $this->assertEquals('0', $results[0]->diff(new \DateTimeImmutable())->format('%s'));
    }

    /**
     * @param class-string<\DateTimeInterface> $class
     *
     * @dataProvider getClasses
     */
    public function testNowWithClock(string $class)
    {
        date_default_timezone_set('Pacific/Honolulu');
        $clock = new MockClock('2022-02-20 22:20:02');
        $resolver = new DateTimeValueResolver($clock);

        $argument = new ArgumentMetadata('dummy', $class, false, false, null, false);
        $request = self::requestWithAttributes(['dummy' => null]);

        $results = $resolver->resolve($request, $argument);

        $this->assertCount(1, $results);
        $this->assertInstanceOf($class, $results[0]);
        $this->assertSame('UTC', $results[0]->getTimezone()->getName(), 'Default timezone');
        $this->assertEquals($clock->now(), $results[0]);
    }

    /**
     * @param class-string<\DateTimeInterface> $class
     *
     * @dataProvider getClasses
     */
    public function testPreviouslyConvertedAttribute(string $class)
    {
        $resolver = new DateTimeValueResolver();

        $argument = new ArgumentMetadata('dummy', $class, false, false, null, true);
        $request = self::requestWithAttributes(['dummy' => $datetime = new \DateTimeImmutable()]);

        $results = $resolver->resolve($request, $argument);

        $this->assertCount(1, $results);
        $this->assertEquals($datetime, $results[0], 'The value is the same, but the class can be modified.');
        $this->assertInstanceOf($class, $results[0]);
    }

    public function testCustomClass()
    {
        date_default_timezone_set('UTC');
        $resolver = new DateTimeValueResolver();

        $argument = new ArgumentMetadata('dummy', FooDateTime::class, false, false, null);
        $request = self::requestWithAttributes(['dummy' => '2016-09-08 00:00:00']);

        $results = $resolver->resolve($request, $argument);

        $this->assertCount(1, $results);
        $this->assertInstanceOf(FooDateTime::class, $results[0]);
        $this->assertEquals('2016-09-08 00:00:00+00:00', $results[0]->format('Y-m-d H:i:sP'));
    }

    /**
     * @dataProvider getTimeZones
     */
    public function testDateTimeImmutable(string $timezone, bool $withClock)
    {
        date_default_timezone_set($withClock ? 'UTC' : $timezone);
        $resolver = new DateTimeValueResolver($withClock ? new MockClock('now', $timezone) : null);

        $argument = new ArgumentMetadata('dummy', \DateTimeImmutable::class, false, false, null);
        $request = self::requestWithAttributes(['dummy' => '2016-09-08 00:00:00 +05:00']);

        $results = $resolver->resolve($request, $argument);

        $this->assertCount(1, $results);
        $this->assertInstanceOf(\DateTimeImmutable::class, $results[0]);
        $this->assertSame('+05:00', $results[0]->getTimezone()->getName(), 'Input timezone');
        $this->assertEquals('2016-09-08 00:00:00', $results[0]->format('Y-m-d H:i:s'));
    }

    /**
     * @dataProvider getTimeZones
     */
    public function testWithFormat(string $timezone, bool $withClock)
    {
        date_default_timezone_set($withClock ? 'UTC' : $timezone);
        $resolver = new DateTimeValueResolver($withClock ? new MockClock('now', $timezone) : null);

        $argument = new ArgumentMetadata('dummy', \DateTimeInterface::class, false, false, null, false, [
            MapDateTime::class => new MapDateTime('m-d-y H:i:s'),
        ]);
        $request = self::requestWithAttributes(['dummy' => '09-08-16 12:34:56']);

        $results = $resolver->resolve($request, $argument);

        $this->assertCount(1, $results);
        $this->assertInstanceOf(\DateTimeImmutable::class, $results[0]);
        $this->assertSame($timezone, $results[0]->getTimezone()->getName(), 'Default timezone');
        $this->assertEquals('2016-09-08 12:34:56', $results[0]->format('Y-m-d H:i:s'));
    }

    public static function provideRelativeDates()
    {
        yield [\DateTimeInterface::class, 'yesterday', '2022-02-19 00:00:00.000000'];
        yield [\DateTime::class, '+1 hour', '2022-02-20 23:20:02.123456'];
        yield [FooDateTime::class, 'noon', '2022-02-20 12:00:00.000000'];
        yield [\DateTimeInterface::class, 'first day of next month', '2022-03-01 22:20:02.123456'];
        yield [\DateTimeInterface::class, '2016-09-08', '2016-09-08 00:00:00.000000'];
        yield [\DateTimeInterface::class, '2016-09-08 +1 hour', '2016-09-08 01:00:00.000000'];
    }

    /**
     * @dataProvider provideRelativeDates
     */
    public function testRelativeDateWithClock(string $class, string $value, string $expected)
    {
        date_default_timezone_set('UTC');
        $resolver = new DateTimeValueResolver(new MockClock('2022-02-20 22:20:02.123456', 'Pacific/Honolulu'));

        $argument = new ArgumentMetadata('dummy', $class, false, false, null);
        $request = self::requestWithAttributes(['dummy' => $value]);

        $results = $resolver->resolve($request, $argument);

        $this->assertCount(1, $results);
        $this->assertInstanceOf($class, $results[0]);
        $this->assertSame('Pacific/Honolulu', $results[0]->getTimezone()->getName());
        $this->assertSame($expected, $results[0]->format('Y-m-d H:i:s.u'));
    }

    public static function provideDatesAroundDstChanges()
    {
        yield ['2018-11-04 16:00:00', 'America/Sao_Paulo', 'Sep 8', '2018-09-08 00:00:00 -03:00'];
        yield ['2018-11-04 16:00:00', 'America/Sao_Paulo', 'Sep 8 +1 hour', '2018-09-08 01:00:00 -03:00'];
        yield ['2018-11-04 16:00:00', 'America/Sao_Paulo', '2016-09-08', '2016-09-08 00:00:00 -03:00'];
        yield ['2026-07-01 12:00:00', 'Europe/Paris', '2025-10-26 02:30', '2025-10-26 02:30:00 +01:00'];
    }

    /**
     * @dataProvider provideDatesAroundDstChanges
     */
    public function testDateWithClockAroundDstChanges(string $now, string $timezone, string $value, string $expected)
    {
        date_default_timezone_set('UTC');
        $resolver = new DateTimeValueResolver(new MockClock($now, $timezone));

        $argument = new ArgumentMetadata('dummy', \DateTimeImmutable::class, false, false, null);
        $request = self::requestWithAttributes(['dummy' => $value]);

        $results = $resolver->resolve($request, $argument);

        $this->assertCount(1, $results);
        $this->assertSame($expected, $results[0]->format('Y-m-d H:i:s P'));
    }

    public static function provideFormatsWithMissingFields()
    {
        yield [\DateTimeInterface::class, 'H:i', '10:30', '2022-02-20 10:30:00.000000'];
        yield [\DateTime::class, 'Y-m-d', '2016-09-08', '2016-09-08 22:20:02.000000'];
        yield [FooDateTime::class, 'd', '05', '2022-02-05 22:20:02.000000'];
        yield [\DateTimeInterface::class, 'Y-m-d|', '2016-09-08', '2016-09-08 00:00:00.000000'];
    }

    /**
     * @dataProvider provideFormatsWithMissingFields
     */
    public function testWithFormatAndClock(string $class, string $format, string $value, string $expected)
    {
        date_default_timezone_set('UTC');
        $resolver = new DateTimeValueResolver(new MockClock('2022-02-20 22:20:02.123456', 'Pacific/Honolulu'));

        $argument = new ArgumentMetadata('dummy', $class, false, false, null, false, [new MapDateTime(format: $format)]);
        $request = self::requestWithAttributes(['dummy' => $value]);

        $results = $resolver->resolve($request, $argument);

        $this->assertCount(1, $results);
        $this->assertInstanceOf($class, $results[0]);
        $this->assertSame('Pacific/Honolulu', $results[0]->getTimezone()->getName());
        $this->assertSame($expected, $results[0]->format('Y-m-d H:i:s.u'));
    }

    public static function provideInvalidDates()
    {
        return [
            'invalid date' => [
                new ArgumentMetadata('dummy', \DateTimeImmutable::class, false, false, null),
                self::requestWithAttributes(['dummy' => 'Invalid DateTime Format']),
            ],
            'invalid format' => [
                new ArgumentMetadata('dummy', \DateTimeImmutable::class, false, false, null, false, [new MapDateTime(format: 'd.m.Y')]),
                self::requestWithAttributes(['dummy' => '2012-07-21']),
            ],
            'invalid ymd format' => [
                new ArgumentMetadata('dummy', \DateTimeImmutable::class, false, false, null, false, [new MapDateTime(format: 'Y-m-d')]),
                self::requestWithAttributes(['dummy' => '2012-21-07']),
            ],
        ];
    }

    /**
     * @dataProvider provideInvalidDates
     */
    public function test404Exception(ArgumentMetadata $argument, Request $request)
    {
        $resolver = new DateTimeValueResolver();

        $this->expectException(NotFoundHttpException::class);
        $this->expectExceptionMessage('Invalid date given for parameter "dummy".');

        $resolver->resolve($request, $argument);
    }

    private static function requestWithAttributes(array $attributes): Request
    {
        $request = Request::create('/');

        foreach ($attributes as $name => $value) {
            $request->attributes->set($name, $value);
        }

        return $request;
    }
}

class FooDateTime extends \DateTimeImmutable
{
}
