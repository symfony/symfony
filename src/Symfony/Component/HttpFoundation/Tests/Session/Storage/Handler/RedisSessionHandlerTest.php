<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpFoundation\Tests\Session\Storage\Handler;

use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\HttpFoundation\Session\Storage\Handler\SessionHandlerFactory;

#[Group('integration')]
class RedisSessionHandlerTest extends AbstractRedisSessionHandlerTestCase
{
    public function testShutdownClosesALazyConnection()
    {
        $handler = SessionHandlerFactory::createHandler('redis://'.getenv('REDIS_HOST'), ['prefix' => self::PREFIX]);
        $redis = (new \ReflectionProperty($handler, 'redis'))->getValue($handler);
        $handler->read('id');

        $this->assertTrue($redis->isLazyObjectInitialized());

        $handler->shutdown();

        $this->assertFalse($redis->isLazyObjectInitialized());
        $this->assertTrue($handler->write('id', 'data'));
        $this->assertSame('data', $handler->read('id'));
    }

    public function testShutdownKeepsAConnectionThatIsNotLazy()
    {
        $this->storage->read('id');
        $this->storage->shutdown();

        $this->assertTrue($this->redisClient->isConnected());
    }

    protected function createRedisClient(string $host): \Redis
    {
        $client = new \Redis();
        $client->connect(...explode(':', $host));

        return $client;
    }
}
