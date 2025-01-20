<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\RateLimiter\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Bridge\PhpUnit\ClockMock;
use Symfony\Component\OptionsResolver\Exception\MissingOptionsException;
use Symfony\Component\RateLimiter\Policy\FixedWindowLimiter;
use Symfony\Component\RateLimiter\Policy\NoLimiter;
use Symfony\Component\RateLimiter\Policy\SlidingWindowLimiter;
use Symfony\Component\RateLimiter\Policy\TokenBucketLimiter;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\Storage\InMemoryStorage;

class RateLimiterFactoryTest extends TestCase
{
    /**
     * @dataProvider validConfigProvider
     */
    public function testValidConfig(string $expectedClass, array $config)
    {
        $factory = new RateLimiterFactory($config, new InMemoryStorage());
        $rateLimiter = $factory->create('key');
        $this->assertInstanceOf($expectedClass, $rateLimiter);
    }

    public static function validConfigProvider()
    {
        yield [TokenBucketLimiter::class, [
            'policy' => 'token_bucket',
            'id' => 'test',
            'limit' => 5,
            'rate' => [
                'interval' => '5 seconds',
            ],
        ]];
        yield [FixedWindowLimiter::class, [
            'policy' => 'fixed_window',
            'id' => 'test',
            'limit' => 5,
            'interval' => '5 seconds',
        ]];
        yield [SlidingWindowLimiter::class, [
            'policy' => 'sliding_window',
            'id' => 'test',
            'limit' => 5,
            'interval' => '5 seconds',
        ]];
        yield [NoLimiter::class, [
            'policy' => 'no_limit',
            'id' => 'test',
        ]];
    }

    /**
     * @dataProvider invalidConfigProvider
     */
    public function testInvalidConfig(string $exceptionClass, array $config)
    {
        $this->expectException($exceptionClass);

        $factory = new RateLimiterFactory($config, new InMemoryStorage());
    }

    public static function invalidConfigProvider()
    {
        yield [MissingOptionsException::class, [
            'policy' => 'token_bucket',
        ]];
    }

    /**
     * @group time-sensitive
     */
    public function testExpirationTimeCalculationWhenUsingDefaultTimezoneRomeWithIntervalAfterCETChange()
    {
        $originalTimezone = date_default_timezone_get();
        try {
            // Timestamp for 'Sun 27 Oct 2024 12:59:40 AM UTC' that's just 20 seconds before switch CEST->CET
            ClockMock::withClockMock(1729990780);

            // This is a prerequisite for the bug to happen
            date_default_timezone_set('Europe/Rome');

            $storage = new InMemoryStorage();
            $factory = new RateLimiterFactory(
                [
                    'id' => 'id_1',
                    'policy' => 'fixed_window',
                    'limit' => 30,
                    'interval' => '21 seconds',
                ],
                $storage
            );
            $rateLimiter = $factory->create('key');
            $rateLimiter->consume(1);
            $limiterState = $storage->fetch('id_1-key');
            // As expected the expiration is equal to the interval we defined
            $this->assertSame(21, $limiterState->getExpirationTime());
        } finally {
            date_default_timezone_set($originalTimezone);
        }
    }
}
