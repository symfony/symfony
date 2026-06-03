<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Console\Tests\ArgumentResolver\ValueResolver;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Console\ArgumentResolver\ValueResolver\DateTimeValueResolver;
use Symfony\Component\Console\Attribute\MapDateTime;
use Symfony\Component\Console\Attribute\Reflection\ReflectionMember;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputOption;

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
        yield [\DateTimeImmutable::class];
        yield [FooDateTime::class];
    }

    public function testUnsupportedArgument()
    {
        $resolver = new DateTimeValueResolver();
        $input = new ArrayInput(['created-at' => 'now'], new InputDefinition([
            new InputArgument('created-at'),
        ]));

        $function = static fn (string $createdAt) => null;
        $reflection = new \ReflectionFunction($function);
        $parameter = $reflection->getParameters()[0];
        $member = new ReflectionMember($parameter);

        $this->assertSame([], $resolver->resolve('createdAt', $input, $member));
    }

    #[DataProvider('getTimeZones')]
    public function testFullDate(string $timezone, bool $withClock)
    {
        date_default_timezone_set($withClock ? 'UTC' : $timezone);
        $resolver = new DateTimeValueResolver($withClock ? new MockClock('now', $timezone) : null);

        $input = new ArrayInput(['created-at' => '2012-07-21 00:00:00'], new InputDefinition([
            new InputArgument('created-at'),
        ]));

        $function = static fn (\DateTimeImmutable $createdAt) => null;
        $reflection = new \ReflectionFunction($function);
        $parameter = $reflection->getParameters()[0];
        $member = new ReflectionMember($parameter);

        $results = $resolver->resolve('createdAt', $input, $member);

        $this->assertCount(1, $results);
        $this->assertInstanceOf(\DateTimeImmutable::class, $results[0]);
        $this->assertSame($timezone, $results[0]->getTimezone()->getName(), 'Default timezone');
        $this->assertEquals('2012-07-21 00:00:00', $results[0]->format('Y-m-d H:i:s'));
    }

    #[DataProvider('getTimeZones')]
    public function testUnixTimestamp(string $timezone, bool $withClock)
    {
        date_default_timezone_set($withClock ? 'UTC' : $timezone);
        $resolver = new DateTimeValueResolver($withClock ? new MockClock('now', $timezone) : null);

        $input = new ArrayInput(['created-at' => '989541720'], new InputDefinition([
            new InputArgument('created-at'),
        ]));

        $function = static fn (\DateTimeImmutable $createdAt) => null;
        $reflection = new \ReflectionFunction($function);
        $parameter = $reflection->getParameters()[0];
        $member = new ReflectionMember($parameter);

        $results = $resolver->resolve('createdAt', $input, $member);

        $this->assertCount(1, $results);
        $this->assertInstanceOf(\DateTimeImmutable::class, $results[0]);
        $this->assertSame('+00:00', $results[0]->getTimezone()->getName(), 'Timestamps are UTC');
        $this->assertEquals('2001-05-11 00:42:00', $results[0]->format('Y-m-d H:i:s'));
    }

    public function testNullableWithEmptyArgument()
    {
        $resolver = new DateTimeValueResolver();
        $input = new ArrayInput(['created-at' => ''], new InputDefinition([
            new InputArgument('created-at'),
        ]));

        $function = static fn (?\DateTimeImmutable $createdAt) => null;
        $reflection = new \ReflectionFunction($function);
        $parameter = $reflection->getParameters()[0];
        $member = new ReflectionMember($parameter);

        $results = $resolver->resolve('createdAt', $input, $member);

        $this->assertCount(1, $results);
        $this->assertNull($results[0]);
    }

    /**
     * @param class-string<\DateTimeInterface> $class
     */
    #[DataProvider('getClasses')]
    public function testNow(string $class)
    {
        date_default_timezone_set($timezone = 'Pacific/Honolulu');
        $resolver = new DateTimeValueResolver();

        $input = new ArrayInput([], new InputDefinition([
            new InputArgument('created-at', InputArgument::OPTIONAL),
        ]));

        $command = new class {
            public function __invoke(\DateTimeInterface $createdAt)
            {
            }
        };
        $reflection = new \ReflectionMethod($command, '__invoke');
        $parameter = $reflection->getParameters()[0];

        // Replace type with the class we're testing
        $function = eval(\sprintf('return fn (%s $createdAt) => null;', $class));
        $reflection = new \ReflectionFunction($function);
        $parameter = $reflection->getParameters()[0];
        $member = new ReflectionMember($parameter);

        $results = $resolver->resolve('createdAt', $input, $member);

        $this->assertCount(1, $results);
        $this->assertInstanceOf($class, $results[0]);
        $this->assertSame($timezone, $results[0]->getTimezone()->getName(), 'Default timezone');
        $this->assertEquals('0', $results[0]->diff(new \DateTimeImmutable())->format('%s'));
    }

    /**
     * @param class-string<\DateTimeInterface> $class
     */
    #[DataProvider('getClasses')]
    public function testNowWithClock(string $class)
    {
        date_default_timezone_set('Pacific/Honolulu');
        $clock = new MockClock('2022-02-20 22:20:02');
        $resolver = new DateTimeValueResolver($clock);

        $input = new ArrayInput([], new InputDefinition([
            new InputArgument('created-at', InputArgument::OPTIONAL),
        ]));

        $function = eval(\sprintf('return fn (%s $createdAt) => null;', $class));
        $reflection = new \ReflectionFunction($function);
        $parameter = $reflection->getParameters()[0];
        $member = new ReflectionMember($parameter);

        $results = $resolver->resolve('createdAt', $input, $member);

        $this->assertCount(1, $results);
        $this->assertInstanceOf($class, $results[0]);
        $this->assertSame('UTC', $results[0]->getTimezone()->getName(), 'Default timezone');
        $this->assertEquals($clock->now(), $results[0]);
    }

    /**
     * @param class-string<\DateTimeInterface> $class
     */
    #[DataProvider('getClasses')]
    public function testPreviouslyConvertedArgument(string $class)
    {
        $resolver = new DateTimeValueResolver();
        $datetime = new \DateTimeImmutable();
        $input = new ArrayInput(['created-at' => $datetime], new InputDefinition([
            new InputArgument('created-at'),
        ]));

        $function = eval(\sprintf('return fn (%s $createdAt) => null;', $class));
        $reflection = new \ReflectionFunction($function);
        $parameter = $reflection->getParameters()[0];
        $member = new ReflectionMember($parameter);

        $results = $resolver->resolve('createdAt', $input, $member);

        $this->assertCount(1, $results);
        $this->assertEquals($datetime, $results[0], 'The value is the same, but the class can be modified.');
        $this->assertInstanceOf($class, $results[0]);
    }

    public function testCustomClass()
    {
        date_default_timezone_set('UTC');
        $resolver = new DateTimeValueResolver();

        $input = new ArrayInput(['created-at' => '2016-09-08 00:00:00'], new InputDefinition([
            new InputArgument('created-at'),
        ]));

        $function = static fn (FooDateTime $createdAt) => null;
        $reflection = new \ReflectionFunction($function);
        $parameter = $reflection->getParameters()[0];
        $member = new ReflectionMember($parameter);

        $results = $resolver->resolve('createdAt', $input, $member);

        $this->assertCount(1, $results);
        $this->assertInstanceOf(FooDateTime::class, $results[0]);
        $this->assertEquals('2016-09-08 00:00:00+00:00', $results[0]->format('Y-m-d H:i:sP'));
    }

    #[DataProvider('getTimeZones')]
    public function testDateTimeImmutable(string $timezone, bool $withClock)
    {
        date_default_timezone_set($withClock ? 'UTC' : $timezone);
        $resolver = new DateTimeValueResolver($withClock ? new MockClock('now', $timezone) : null);

        $input = new ArrayInput(['created-at' => '2016-09-08 00:00:00 +05:00'], new InputDefinition([
            new InputArgument('created-at'),
        ]));

        $function = static fn (\DateTimeImmutable $createdAt) => null;
        $reflection = new \ReflectionFunction($function);
        $parameter = $reflection->getParameters()[0];
        $member = new ReflectionMember($parameter);

        $results = $resolver->resolve('createdAt', $input, $member);

        $this->assertCount(1, $results);
        $this->assertInstanceOf(\DateTimeImmutable::class, $results[0]);
        $this->assertSame('+05:00', $results[0]->getTimezone()->getName(), 'Input timezone');
        $this->assertEquals('2016-09-08 00:00:00', $results[0]->format('Y-m-d H:i:s'));
    }

    #[DataProvider('getTimeZones')]
    public function testWithFormat(string $timezone, bool $withClock)
    {
        date_default_timezone_set($withClock ? 'UTC' : $timezone);
        $resolver = new DateTimeValueResolver($withClock ? new MockClock('now', $timezone) : null);

        $input = new ArrayInput(['created-at' => '09-08-16 12:34:56'], new InputDefinition([
            new InputArgument('created-at'),
        ]));

        $command = new class {
            public function __invoke(
                #[MapDateTime(format: 'm-d-y H:i:s')]
                \DateTimeInterface $createdAt,
            ) {
            }
        };
        $reflection = new \ReflectionMethod($command, '__invoke');
        $parameter = $reflection->getParameters()[0];
        $member = new ReflectionMember($parameter);

        $results = $resolver->resolve('createdAt', $input, $member);

        $this->assertCount(1, $results);
        $this->assertInstanceOf(\DateTimeImmutable::class, $results[0]);
        $this->assertSame($timezone, $results[0]->getTimezone()->getName(), 'Default timezone');
        $this->assertEquals('2016-09-08 12:34:56', $results[0]->format('Y-m-d H:i:s'));
    }

    public function testWithOption()
    {
        date_default_timezone_set('UTC');
        $resolver = new DateTimeValueResolver();

        $input = new ArrayInput(['--created-at' => '2016-09-08 00:00:00'], new InputDefinition([
            new InputOption('created-at'),
        ]));

        $command = new class {
            public function __invoke(
                #[MapDateTime(option: 'created-at')]
                \DateTimeImmutable $createdAt,
            ) {
            }
        };
        $reflection = new \ReflectionMethod($command, '__invoke');
        $parameter = $reflection->getParameters()[0];
        $member = new ReflectionMember($parameter);

        $results = $resolver->resolve('createdAt', $input, $member);

        $this->assertCount(1, $results);
        $this->assertInstanceOf(\DateTimeImmutable::class, $results[0]);
        $this->assertEquals('2016-09-08 00:00:00', $results[0]->format('Y-m-d H:i:s'));
    }

    public static function provideInvalidDates()
    {
        return [
            'invalid date' => ['Invalid DateTime Format'],
            'invalid format' => ['2012-07-21', 'd.m.Y'],
            'invalid ymd format' => ['2012-21-07', 'Y-m-d'],
        ];
    }

    #[DataProvider('provideInvalidDates')]
    public function testRuntimeException(string $value, ?string $format = null)
    {
        $resolver = new DateTimeValueResolver();

        $input = new ArrayInput(['created-at' => $value], new InputDefinition([
            new InputArgument('created-at'),
        ]));

        if ($format) {
            $command = eval(\sprintf('return new class {
                public function __invoke(
                    #[\\Symfony\\Component\\Console\\Attribute\\MapDateTime(format: "%s")]
                    \\DateTimeImmutable $createdAt
                ) {}
            };', $format));
            $reflection = new \ReflectionMethod($command, '__invoke');
            $parameter = $reflection->getParameters()[0];
            $member = new ReflectionMember($parameter);
        } else {
            $function = static fn (\DateTimeImmutable $createdAt) => null;
            $reflection = new \ReflectionFunction($function);
            $parameter = $reflection->getParameters()[0];
            $member = new ReflectionMember($parameter);
        }

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Invalid date given for parameter "$createdAt".');

        $resolver->resolve('createdAt', $input, $member);
    }
}

class FooDateTime extends \DateTimeImmutable
{
}
