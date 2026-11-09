<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Lock\Tests\Store;

use PHPUnit\Framework\Attributes\Group;
use Predis\Command\CommandInterface;
use Predis\Connection\Parameters;
use Predis\Connection\StreamConnection;
use Predis\Response\ServerException;
use Symfony\Component\Lock\Exception\LockStorageException;
use Symfony\Component\Lock\Key;
use Symfony\Component\Lock\Store\RedisStore;

/**
 * @author Jérémy Derussé <jeremy@derusse.com>
 */
#[Group('integration')]
class PredisStoreWithoutExceptionsTest extends AbstractRedisStoreTestCase
{
    public static function setUpBeforeClass(): void
    {
        $redis = new \Predis\Client(
            array_combine(['host', 'port'], explode(':', getenv('REDIS_HOST')) + [1 => null]),
            ['exceptions' => false],
        );
        try {
            $redis->connect();
        } catch (\Exception $e) {
            self::markTestSkipped($e->getMessage());
        }
    }

    protected function getRedisConnection(): \Predis\Client
    {
        $redis = new \Predis\Client(
            array_combine(['host', 'port'], explode(':', getenv('REDIS_HOST')) + [1 => null]),
            ['exceptions' => false],
        );
        $redis->connect();

        return $redis;
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

        $this->expectException(LockStorageException::class);
        $this->expectExceptionMessage('READONLY');
        $store->save(new Key(__METHOD__));
    }
}
