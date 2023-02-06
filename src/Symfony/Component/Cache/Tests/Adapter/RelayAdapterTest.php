<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Cache\Tests\Adapter;

use PHPUnit\Framework\SkippedTestSuiteError;
use Relay\Relay;
use Symfony\Component\Cache\Adapter\AbstractAdapter;
use Symfony\Component\Cache\Adapter\RedisAdapter;
use Symfony\Component\Cache\Exception\InvalidArgumentException;
use Symfony\Component\Cache\Traits\RelayProxy;

/**
 * @requires extension relay
 *
 * @group integration
 */
class RelayAdapterTest extends AbstractRedisAdapterTestCase
{
    public static function setUpBeforeClass(): void
    {
        try {
            new Relay(...explode(':', getenv('REDIS_HOST')));
        } catch (\Relay\Exception $e) {
            throw new SkippedTestSuiteError(getenv('REDIS_HOST').': '.$e->getMessage());
        }
        self::$redis = AbstractAdapter::createConnection('redis://'.getenv('REDIS_HOST'), ['lazy' => true, 'class' => Relay::class]);
        self::assertInstanceOf(RelayProxy::class, self::$redis);
    }

    public function testCreateHostConnection()
    {
        $redis = RedisAdapter::createConnection('redis://'.getenv('REDIS_HOST').'?class=Relay\Relay');
        $this->assertInstanceOf(Relay::class, $redis);
        $this->assertTrue($redis->isConnected());
        $this->assertSame(0, $redis->getDbNum());
    }

    public function testLazyConnection()
    {
        $redis = RedisAdapter::createConnection('redis://nonexistenthost?class=Relay\Relay&lazy=1');
        $this->assertInstanceOf(RelayProxy::class, $redis);
        // no exception until now
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Failed to resolve host address');
        $redis->getHost(); // yep, only here exception is thrown
    }
}
