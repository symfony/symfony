<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Bridge\MongoDb\Tests\Transport;

use MongoDB\Client;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Bridge\MongoDb\Transport\Connection;

#[RequiresPhpExtension('mongodb')]
#[Group('integration')]
class MongoDbTransportReplicaSetIntegrationTest extends TestCase
{
    private const DATABASE = 'messenger_tests';

    private Client $client;

    private static ?bool $replicaSet = null;

    protected function setUp(): void
    {
        if (!class_exists(Client::class)) {
            $this->markTestSkipped('The "mongodb/mongodb" package is required.');
        }

        if (null === self::$replicaSet) {
            try {
                $client = new Client($this->replicaSetUri(), ['serverSelectionTimeoutMS' => 2000]);
                $hello = $client->getDatabase('admin')->command(['hello' => 1])->toArray()[0];
                self::$replicaSet = isset($hello['setName']) || isset($hello->setName);
                $this->client = $client;
            } catch (\Throwable) {
                self::$replicaSet = false;
            }
        }

        if (!self::$replicaSet) {
            if (false !== getenv('MONGODB_RS_URI')) {
                self::fail('The replica set is not reachable although MONGODB_RS_URI was provided.');
            }

            $this->markTestSkipped('Change streams require a replica set.');
        }

        $this->client->getCollection(self::DATABASE, 'messenger_messages')->deleteMany(['queueName' => 'default']);
    }

    public function testChangeStreamWakesUpAnOpenStream()
    {
        if (!\function_exists('proc_open')) {
            $this->markTestSkipped('proc_open is required to run the wake-up test.');
        }

        // 15 s leaves headroom for the producer subprocess to boot and connect
        $streamConnection = Connection::fromDsn($this->replicaSetUri(), ['wait_time' => 15, 'database' => self::DATABASE], $this->client);

        // a subprocess inserts the message about 400 ms after this test starts,
        // while the get() call below is already blocked on the change stream
        $code = \sprintf(
            'require %s; usleep(400000); $client = new MongoDB\Client(%s, ["serverSelectionTimeoutMS" => 5000]); $connection = Symfony\Component\Messenger\Bridge\MongoDb\Transport\Connection::fromDsn(%s, ["database" => %s], $client); $connection->send("streamed");',
            var_export(\dirname(__DIR__, 8).'/vendor/autoload.php', true),
            var_export($this->replicaSetUri(), true),
            var_export($this->replicaSetUri(), true),
            var_export(self::DATABASE, true)
        );

        $producer = proc_open([\PHP_BINARY, '-r', $code], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);

        $start = microtime(true);
        $document = $streamConnection->get();
        $elapsed = microtime(true) - $start;

        if (\is_resource($producer)) {
            fclose($pipes[1]);
            fclose($pipes[2]);
            proc_close($producer);
        }

        $this->assertNotNull($document);
        $this->assertSame('streamed', $document['body']);
        // the message was inserted after the get() call started: a poll query
        // could never return it, only the change stream wait waking up could
        $this->assertGreaterThanOrEqual(0.15, $elapsed);
    }

    private function replicaSetUri(): string
    {
        return getenv('MONGODB_RS_URI') ?: 'mongodb://localhost:27017';
    }
}
