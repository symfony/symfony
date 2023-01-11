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
        yield ['UTC'];
        yield ['Pacific/Honolulu'];
        yield ['America/Toronto'];
    }

    public static function getClasses()
    {
        yield [\DateTimeInterface::class];
        yield [\DateTime::class];
        yield [FooDateTime::class];
    }

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

    /**
     * @dataProvider getTimeZones
     */
    public function testFullDate(string $timezone)
    {
        date_default_timezone_set($timezone);
        $resolver = new DateTimeValueResolver();

        $argument = new ArgumentMetadata('dummy', \DateTime::class, false, false, null);
        $request = self::requestWithAttributes(['dummy' => '2012-07-21 00:00:00']);

        /** @var \Generator $results */
        $results = $resolver->resolve($request, $argument);
        $results = iterator_to_array($results);

        $this->assertCount(1, $results);
        $this->assertInstanceOf(\DateTime::class, $results[0]);
        $this->assertSame($timezone, $results[0]->getTimezone()->getName(), 'Default timezone');
        $this->assertEquals('2012-07-21 00:00:00', $results[0]->format('Y-m-d H:i:s'));
    }

    /**
     * @dataProvider getTimeZones
     */
    public function testUnixTimestamp(string $timezone)
    {
        date_default_timezone_set($timezone);
        $resolver = new DateTimeValueResolver();

        $argument = new ArgumentMetadata('dummy', \DateTime::class, false, false, null);
        $request = self::requestWithAttributes(['dummy' => '989541720']);

        /** @var \Generator $results */
        $results = $resolver->resolve($request, $argument);
        $results = iterator_to_array($results);

        $this->assertCount(1, $results);
        $this->assertInstanceOf(\DateTime::class, $results[0]);
        $this->assertSame('+00:00', $results[0]->getTimezone()->getName(), 'Timestamps are UTC');
        $this->assertEquals('2001-05-11 00:42:00', $results[0]->format('Y-m-d H:i:s'));
    }

    public function testNullableWithEmptyAttribute()
    {
        $resolver = new DateTimeValueResolver();

        $argument = new ArgumentMetadata('dummy', \DateTime::class, false, false, null, true);
        $request = self::requestWithAttributes(['dummy' => '']);

        /** @var \Generator $results */
        $results = $resolver->resolve($request, $argument);
        $results = iterator_to_array($results);

        $this->assertCount(1, $results);
        $this->assertNull($results[0]);
    }

    /**
     * @dataProvider getTimeZones
     */
    public function testNow(string $timezone)
    {
        date_default_timezone_set($timezone);
        $resolver = new DateTimeValueResolver();

        $argument = new ArgumentMetadata('dummy', \DateTime::class, false, false, null, false);
        $request = self::requestWithAttributes(['dummy' => null]);

        /** @var \Generator $results */
        $results = $resolver->resolve($request, $argument);
        $results = iterator_to_array($results);

        $this->assertCount(1, $results);
        $this->assertEquals('0', $results[0]->diff(new \DateTimeImmutable())->format('%s'));
        $this->assertSame($timezone, $results[0]->getTimezone()->getName(), 'Default timezone');
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
        $request = self::requestWithAttributes(['dummy' => $datetime = new \DateTime()]);

        /** @var \Generator $results */
        $results = $resolver->resolve($request, $argument);
        $results = iterator_to_array($results);

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

        /** @var \Generator $results */
        $results = $resolver->resolve($request, $argument);
        $results = iterator_to_array($results);

        $this->assertCount(1, $results);
        $this->assertInstanceOf(FooDateTime::class, $results[0]);
        $this->assertEquals('2016-09-08 00:00:00+00:00', $results[0]->format('Y-m-d H:i:sP'));
    }

    /**
     * @dataProvider getTimeZones
     */
    public function testDateTimeImmutable(string $timezone)
    {
        date_default_timezone_set($timezone);
        $resolver = new DateTimeValueResolver();

        $argument = new ArgumentMetadata('dummy', \DateTimeImmutable::class, false, false, null);
        $request = self::requestWithAttributes(['dummy' => '2016-09-08 00:00:00 +05:00']);

        /** @var \Generator $results */
        $results = $resolver->resolve($request, $argument);
        $results = iterator_to_array($results);

        $this->assertCount(1, $results);
        $this->assertInstanceOf(\DateTimeImmutable::class, $results[0]);
        $this->assertSame('+05:00', $results[0]->getTimezone()->getName(), 'Input timezone');
        $this->assertEquals('2016-09-08 00:00:00', $results[0]->format('Y-m-d H:i:s'));
    }

    /**
     * @dataProvider getTimeZones
     */
    public function testWithFormat(string $timezone)
    {
        date_default_timezone_set($timezone);
        $resolver = new DateTimeValueResolver();

        $argument = new ArgumentMetadata('dummy', \DateTimeInterface::class, false, false, null, false, [
            MapDateTime::class => new MapDateTime('m-d-y H:i:s'),
        ]);
        $request = self::requestWithAttributes(['dummy' => '09-08-16 12:34:56']);

        /** @var \Generator $results */
        $results = $resolver->resolve($request, $argument);
        $results = iterator_to_array($results);

        $this->assertCount(1, $results);
        $this->assertInstanceOf(\DateTimeImmutable::class, $results[0]);
        $this->assertSame($timezone, $results[0]->getTimezone()->getName(), 'Default timezone');
        $this->assertEquals('2016-09-08 12:34:56', $results[0]->format('Y-m-d H:i:s'));
    }

    public static function provideInvalidDates()
    {
        return [
            'invalid date' => [
                new ArgumentMetadata('dummy', \DateTime::class, false, false, null),
                self::requestWithAttributes(['dummy' => 'Invalid DateTime Format']),
            ],
            'invalid format' => [
                new ArgumentMetadata('dummy', \DateTime::class, false, false, null, false, [new MapDateTime(format: 'd.m.Y')]),
                self::requestWithAttributes(['dummy' => '2012-07-21']),
            ],
            'invalid ymd format' => [
                new ArgumentMetadata('dummy', \DateTime::class, false, false, null, false, [new MapDateTime(format: 'Y-m-d')]),
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

        /** @var \Generator $results */
        $results = $resolver->resolve($request, $argument);
        iterator_to_array($results);
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

class FooDateTime extends \DateTime
{
}
