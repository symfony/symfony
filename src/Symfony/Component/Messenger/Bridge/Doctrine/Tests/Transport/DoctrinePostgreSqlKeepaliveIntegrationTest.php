<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Bridge\Doctrine\Tests\Transport;

use Doctrine\DBAL\Configuration;
use Doctrine\DBAL\Connection as DBALConnection;
use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Driver\Middleware;
use Doctrine\DBAL\Driver\Middleware\AbstractConnectionMiddleware;
use Doctrine\DBAL\Driver\Middleware\AbstractDriverMiddleware;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Schema\DefaultSchemaManagerFactory;
use Doctrine\DBAL\Tools\DsnParser;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Bridge\Doctrine\Transport\Connection;

/**
 * Reproduces the messenger:consume --keepalive SIGALRM race: keepalive is
 * delivered by a signal handler that pcntl async signals can run between any
 * two opcodes, including inside a transport transaction's commit, after the
 * driver-level COMMIT has completed but before DBAL decrements its
 * transaction nesting counter. A keepalive touching the transport connection
 * in that window issues SAVEPOINT against an idle session, corrupts the
 * counter, and fails the interrupted send even though its INSERT committed.
 *
 * The driver middleware below raises SIGALRM to the current process right
 * after the armed driver-level COMMIT returns, which is by construction
 * inside that window.
 */
#[RequiresPhpExtension('pdo_pgsql')]
#[RequiresPhpExtension('pcntl')]
#[RequiresPhpExtension('posix')]
#[Group('integration')]
class DoctrinePostgreSqlKeepaliveIntegrationTest extends TestCase
{
    private DBALConnection $driverConnection;
    private Connection $connection;
    private \stdClass $alarm;

    protected function setUp(): void
    {
        if (!$host = getenv('POSTGRES_HOST')) {
            $this->markTestSkipped('Missing POSTGRES_HOST env variable');
        }

        $this->alarm = new \stdClass();
        $this->alarm->armed = false;

        $url = "pdo-pgsql://postgres:password@$host";
        $params = (new DsnParser())->parse($url);
        $config = new Configuration();
        if (class_exists(DefaultSchemaManagerFactory::class)) {
            $config->setSchemaManagerFactory(new DefaultSchemaManagerFactory());
        }
        $config->setMiddlewares([$this->createAlarmOnCommitMiddleware($this->alarm)]);

        $this->driverConnection = DriverManager::getConnection($params, $config);
        $this->connection = new Connection(['table_name' => 'keepalive_queue_table'], $this->driverConnection);
        $this->connection->setup();
    }

    protected function tearDown(): void
    {
        if (\function_exists('pcntl_signal')) {
            pcntl_signal(\SIGALRM, \SIG_DFL);
        }

        if (!isset($this->driverConnection)) {
            return;
        }
        $this->driverConnection->createSchemaManager()->dropTable('keepalive_queue_table');
        $this->driverConnection->close();
    }

    public function testKeepaliveInterruptingASendCommitLeavesTheSendIntact()
    {
        $inFlightId = $this->connection->send('{"message": "in-flight"}', []);

        // What ConsumeMessagesCommand::handleSignal() runs when the keepalive alarm fires
        $connection = $this->connection;
        pcntl_async_signals(true);
        pcntl_signal(\SIGALRM, static function () use ($connection, $inFlightId): void {
            $connection->keepalive($inFlightId);
        });

        $this->alarm->armed = true;
        $victimId = $this->connection->send('{"message": "victim"}', []);

        $this->assertSame(1, (int) $this->driverConnection->fetchOne('SELECT COUNT(*) FROM keepalive_queue_table WHERE id = ?', [$victimId]), 'The interrupted send must store the message exactly once.');
        $this->assertNotNull($this->driverConnection->fetchOne('SELECT delivered_at FROM keepalive_queue_table WHERE id = ?', [$inFlightId]), 'The keepalive must have renewed the delivered_at lease.');
        $this->assertFalse($this->driverConnection->isTransactionActive(), 'The transport connection must come out of the interrupted send with a clean transaction state.');
    }

    private function createAlarmOnCommitMiddleware(\stdClass $alarm): Middleware
    {
        return new class($alarm) implements Middleware {
            public function __construct(private \stdClass $alarm)
            {
            }

            public function wrap(Driver $driver): Driver
            {
                return new class($driver, $this->alarm) extends AbstractDriverMiddleware {
                    public function __construct(Driver $driver, private \stdClass $alarm)
                    {
                        parent::__construct($driver);
                    }

                    public function connect(#[\SensitiveParameter] array $params): Driver\Connection
                    {
                        return new class(parent::connect($params), $this->alarm) extends AbstractConnectionMiddleware {
                            public function __construct(Driver\Connection $connection, private \stdClass $alarm)
                            {
                                parent::__construct($connection);
                            }

                            public function commit(): void
                            {
                                parent::commit();

                                if ($this->alarm->armed) {
                                    $this->alarm->armed = false;
                                    posix_kill(posix_getpid(), \SIGALRM);
                                    usleep(1); // an opcode boundary, so the async signal handler runs before commit() returns to DBAL
                                }
                            }
                        };
                    }
                };
            }
        };
    }
}
