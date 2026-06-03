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
use Predis\Client;
use Symfony\Component\HttpFoundation\Session\Storage\Handler\RedisSessionHandler;
use Symfony\Component\HttpFoundation\Session\Storage\Handler\SessionHandlerFactory;

#[Group('integration')]
class PredisSessionHandlerTest extends AbstractRedisSessionHandlerTestCase
{
    protected function createRedisClient(string $host): Client
    {
        return new Client(array_combine(['host', 'port'], explode(':', getenv('REDIS_HOST')) + [1 => 6379]));
    }

    public function testNoDuplicatePrefixWhenUsingDsn()
    {
        $host = getenv('REDIS_HOST') ?: 'localhost';
        $dsn = 'redis://'.$host.'?prefix=my_session_prefix_&class='.urlencode(Client::class);

        $handler = SessionHandlerFactory::createHandler($dsn);
        $this->assertInstanceOf(RedisSessionHandler::class, $handler);

        $handler->write('test_id', 'test_data');
        $rawClient = new Client(array_combine(['host', 'port'], explode(':', $host) + [1 => 6379]));

        try {
            $this->assertSame(0, $rawClient->exists('my_session_prefix_my_session_prefix_test_id'));
            $this->assertEquals('test_data', $rawClient->get('my_session_prefix_test_id'));
        } finally {
            $rawClient->flushdb();
        }
    }
}
