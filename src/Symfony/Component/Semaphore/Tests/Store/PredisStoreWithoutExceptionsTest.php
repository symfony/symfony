<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Semaphore\Tests\Store;

use PHPUnit\Framework\Attributes\Group;
use Predis\Command\CommandInterface;
use Predis\Connection\Parameters;
use Predis\Connection\StreamConnection;
use Predis\Response\ServerException;
use Symfony\Component\Semaphore\Exception\SemaphoreStorageException;
use Symfony\Component\Semaphore\Key;
use Symfony\Component\Semaphore\Store\RedisStore;

#[Group('integration')]
class PredisStoreWithoutExceptionsTest extends AbstractRedisStoreTestCase
{
    public static function setUpBeforeClass(): void
    {
        $redis = new \Predis\Client(array_combine(['host', 'port'], explode(':', getenv('REDIS_HOST')) + [1 => null]), ['exceptions' => false]);
        try {
            $redis->connect();
        } catch (\Exception $e) {
            self::markTestSkipped($e->getMessage());
        }
    }

    protected function getRedisConnection(): \Predis\Client
    {
        $redis = new \Predis\Client(array_combine(['host', 'port'], explode(':', getenv('REDIS_HOST')) + [1 => null]), ['exceptions' => false]);
        $redis->connect();

        return $redis;
    }

    public function testSaveReportsErrorReplies()
    {
        $resource = uniqid(__METHOD__, true);
        $redis = $this->getRedisConnection();
        $redis->hset('{'.$resource.'}:time', 'field', 'value');

        try {
            $this->expectException(SemaphoreStorageException::class);
            $this->expectExceptionMessage('WRONGTYPE');
            $this->getStore()->save(new Key($resource, 1), 10);
        } finally {
            $redis->del('{'.$resource.'}:time');
        }
    }

    public function testExistsReportsErrorReplies()
    {
        $resource = uniqid(__METHOD__, true);
        $redis = $this->getRedisConnection();
        $redis->hset('{'.$resource.'}:weight', 'field', 'value');

        try {
            $this->expectException(SemaphoreStorageException::class);
            $this->expectExceptionMessage('WRONGTYPE');
            $this->getStore()->exists(new Key($resource, 1));
        } finally {
            $redis->del('{'.$resource.'}:weight');
        }
    }

    public function testSaveReportsErrorRepliesReturnedAsServerExceptions()
    {
        // Predis' RelayConnection returns error replies as ServerException objects instead of Error ones
        $connection = new class(Parameters::create([])) extends StreamConnection {
            public function executeCommand(CommandInterface $command): mixed
            {
                return new ServerException('READONLY You can\'t write against a read only replica.');
            }
        };
        $store = new RedisStore(new \Predis\Client($connection, ['exceptions' => false]));

        $this->expectException(SemaphoreStorageException::class);
        $this->expectExceptionMessage('READONLY');
        $store->save(new Key(__METHOD__, 1), 10);
    }
}
