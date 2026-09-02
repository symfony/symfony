<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Bridge\AmpSql\Tests\Transport;

use Amp\Parallel\Context\ProcessContextFactory;
use Fabpot\Amp\Sqlite\SqliteConfig;
use Fabpot\Amp\Sqlite\SqliteConnectionPool;
use Fabpot\Amp\Sqlite\SqliteConnector;
use Fabpot\Amp\Sqlite\SqliteTransactionMode;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Bridge\AmpSql\Tests\Fixtures\DummyMessage;
use Symfony\Component\Messenger\Bridge\AmpSql\Transport\AmpSqlTransport;
use Symfony\Component\Messenger\Bridge\AmpSql\Transport\Backend\SqliteBackend;
use Symfony\Component\Messenger\Bridge\AmpSql\Transport\Connection;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Transport\Serialization\PhpSerializer;

use function Amp\async;
use function Amp\delay;

final class AmpSqlEventLoopIntegrationTest extends TestCase
{
    private string $databasePath;

    protected function setUp(): void
    {
        $this->databasePath = sys_get_temp_dir().'/symfony-amp-sql-messenger-loop-'.bin2hex(random_bytes(8)).'.db';
    }

    protected function tearDown(): void
    {
        foreach ([$this->databasePath, $this->databasePath.'-shm', $this->databasePath.'-wal'] as $file) {
            @unlink($file);
        }
    }

    public function testQueueOperationSuspendsTheCallingFiber()
    {
        $config = (new SqliteConfig($this->databasePath))
            ->withBusyTimeout(1000)
            ->withTransactionMode(SqliteTransactionMode::Immediate);
        $connector = new SqliteConnector(new ProcessContextFactory(childConnectTimeout: 30));
        $transportPool = new SqliteConnectionPool($config, 1, connector: $connector, transactionIsolation: SqliteTransactionMode::Immediate);
        $lockerPool = new SqliteConnectionPool($config, 1, connector: $connector, transactionIsolation: SqliteTransactionMode::Immediate);
        $transport = new AmpSqlTransport(new Connection($transportPool, new SqliteBackend()), new PhpSerializer());

        try {
            $transport->setup();
            $transaction = $lockerPool->beginTransaction();
            $future = async(static fn () => $transport->send(new Envelope(new DummyMessage('cooperative'))));
            $timerRan = false;
            $timer = async(static function () use (&$timerRan) {
                delay(0.02);
                $timerRan = true;
            });

            $timer->await();
            self::assertTrue($timerRan);
            self::assertFalse($future->isComplete());

            $transaction->commit();
            $future->await();
            self::assertSame(1, $transport->getMessageCount());
        } finally {
            if (isset($transaction) && $transaction->isActive()) {
                $transaction->rollback();
            }
            $transport->close();
            $lockerPool->close();
        }
    }
}
