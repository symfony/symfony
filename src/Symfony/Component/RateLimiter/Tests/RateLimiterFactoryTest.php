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
        $factory->create('key');
    }

    public static function invalidConfigProvider()
    {
        yield [MissingOptionsException::class, [
            'policy' => 'token_bucket',
        ]];
    }
}
