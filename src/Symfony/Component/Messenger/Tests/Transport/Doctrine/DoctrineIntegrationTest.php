<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Tests\Transport\Doctrine;

use Doctrine\DBAL\DriverManager;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Tests\Fixtures\DummyMessage;
use Symfony\Component\Messenger\Transport\Doctrine\Connection;

/**
 * @requires pdo_mysql
 */
class DoctrineIntegrationTest extends TestCase
{
    private $driverConnection;
    private $connection;

    protected function setUp()
    {
        parent::setUp();

        if (!getenv('MESSENGER_DOCTRINE_DSN')) {
            $this->markTestSkipped('The "MESSENGER_DOCTRINE_DSN" environment variable is required.');
        }
        $dsn = getenv('MESSENGER_DOCTRINE_DSN');
        $this->driverConnection = DriverManager::getConnection(['url' => $dsn]);
        $this->connection = new Connection([], $this->driverConnection);
        // call send to auto-setup the table
        $this->connection->setup();
        // ensure the table is clean for tests
        $this->driverConnection->exec('DELETE FROM messenger_messages');
    }

    public function testConnectionSendAndGet()
    {
        $this->connection->send('{"message": "Hi"}', ['type' => DummyMessage::class]);
        $encoded = $this->connection->get();
        $this->assertEquals('{"message": "Hi"}', $encoded['body']);
        $this->assertEquals(['type' => DummyMessage::class], $encoded['headers']);
    }

    public function testSendWithDelay()
    {
        $this->connection->send('{"message": "Hi i am delayed"}', ['type' => DummyMessage::class], 600000);

        $available_at = $this->driverConnection->createQueryBuilder()
            ->select('m.available_at')
            ->from('messenger_messages', 'm')
            ->where('m.body = :body')
            ->setParameter(':body', '{"message": "Hi i am delayed"}')
            ->execute()
            ->fetchColumn();

        $available_at = new \DateTime($available_at);

        $now = \DateTime::createFromFormat('U.u', microtime(true));
        $now->modify('+60 seconds');
        $this->assertGreaterThan($now, $available_at);
    }

    public function testItRetrieveTheFirstAvailableMessage()
    {
        // insert messages
        // one currently handled
        $this->driverConnection->insert('messenger_messages', [
            'body' => '{"message": "Hi handled"}',
            'headers' => json_encode(['type' => DummyMessage::class]),
            'queue_name' => 'default',
            'created_at' => Connection::formatDateTime(new \DateTime('2019-03-15 12:00:00')),
            'available_at' => Connection::formatDateTime(new \DateTime('2019-03-15 12:00:00')),
            'delivered_at' => Connection::formatDateTime(\DateTime::createFromFormat('U.u', microtime(true))),
        ]);
        // one available later
        $this->driverConnection->insert('messenger_messages', [
            'body' => '{"message": "Hi delayed"}',
            'headers' => json_encode(['type' => DummyMessage::class]),
            'queue_name' => 'default',
            'created_at' => Connection::formatDateTime(new \DateTime('2019-03-15 12:00:00')),
            'available_at' => Connection::formatDateTime(new \DateTime('2019-03-15 13:00:00')),
        ]);
        // one available
        $this->driverConnection->insert('messenger_messages', [
            'body' => '{"message": "Hi available"}',
            'headers' => json_encode(['type' => DummyMessage::class]),
            'queue_name' => 'default',
            'created_at' => Connection::formatDateTime(new \DateTime('2019-03-15 12:00:00')),
            'available_at' => Connection::formatDateTime(new \DateTime('2019-03-15 12:30:00')),
        ]);

        $encoded = $this->connection->get();
        $this->assertEquals('{"message": "Hi available"}', $encoded['body']);
    }

    public function testItRetrieveTheMessageThatIsOlderThanRedeliverTimeout()
    {
        $twoHoursAgo = new \DateTime('now');
        $twoHoursAgo->modify('-2 hours');
        $this->driverConnection->insert('messenger_messages', [
            'body' => '{"message": "Hi requeued"}',
            'headers' => json_encode(['type' => DummyMessage::class]),
            'queue_name' => 'default',
            'created_at' => Connection::formatDateTime(new \DateTime('2019-03-15 12:00:00')),
            'available_at' => Connection::formatDateTime(new \DateTime('2019-03-15 12:00:00')),
            'delivered_at' => Connection::formatDateTime($twoHoursAgo),
        ]);
        $this->driverConnection->insert('messenger_messages', [
            'body' => '{"message": "Hi available"}',
            'headers' => json_encode(['type' => DummyMessage::class]),
            'queue_name' => 'default',
            'created_at' => Connection::formatDateTime(new \DateTime('2019-03-15 12:00:00')),
            'available_at' => Connection::formatDateTime(new \DateTime('2019-03-15 12:30:00')),
        ]);

        $next = $this->connection->get();
        $this->assertEquals('{"message": "Hi requeued"}', $next['body']);
        $this->connection->reject($next['id']);
    }
}
