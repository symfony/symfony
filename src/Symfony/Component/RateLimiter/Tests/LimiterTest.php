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
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\RateLimiter\Policy\FixedWindowLimiter;
use Symfony\Component\RateLimiter\Policy\TokenBucketLimiter;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\Storage\StorageInterface;

class LimiterTest extends TestCase
{
    public function testTokenBucket()
    {
        $factory = $this->createFactory([
            'id' => 'test',
            'policy' => 'token_bucket',
            'limit' => 10,
            'rate' => ['interval' => '1 second'],
        ]);
        $limiter = $factory->create('127.0.0.1');

        $this->assertInstanceOf(TokenBucketLimiter::class, $limiter);
    }

    public function testFixedWindow()
    {
        $factory = $this->createFactory([
            'id' => 'test',
            'policy' => 'fixed_window',
            'limit' => 10,
            'interval' => '1 minute',
        ]);
        $limiter = $factory->create();

        $this->assertInstanceOf(FixedWindowLimiter::class, $limiter);
    }

    public function testWrongInterval()
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Cannot parse interval "1 minut", please use a valid unit as described on https://www.php.net/datetime.formats.relative.');

        $this->createFactory([
            'id' => 'test',
            'policy' => 'fixed_window',
            'limit' => 10,
            'interval' => '1 minut',
        ]);
    }

    private function createFactory(array $options)
    {
        return new RateLimiterFactory($options, $this->createMock(StorageInterface::class), $this->createMock(LockFactory::class));
    }
}
