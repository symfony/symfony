<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Bridge\AmazonSqs\Tests\Transport;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Bridge\AmazonSqs\Tests\Fixtures\DummyMessage;
use Symfony\Component\Messenger\Bridge\AmazonSqs\Transport\Connection;

class AmazonSqsIntegrationTest extends TestCase
{
    private $connection;

    protected function setUp(): void
    {
        if (!getenv('MESSENGER_SQS_DSN')) {
            $this->markTestSkipped('The "MESSENGER_SQS_DSN" environment variable is required.');
        }

        $this->connection = Connection::fromDsn(getenv('MESSENGER_SQS_DSN'), []);
        $this->connection->setup();
        $this->clearSqs();
    }

    public function testConnectionSendAndGet()
    {
        $this->connection->send('{"message": "Hi"}', ['type' => DummyMessage::class]);
        $this->assertSame(1, $this->connection->getMessageCount());

        $wait = 0;
        while ((null === $encoded = $this->connection->get()) && $wait++ < 200) {
            usleep(5000);
        }

        $this->assertEquals('{"message": "Hi"}', $encoded['body']);
        $this->assertEquals(['type' => DummyMessage::class], $encoded['headers']);
    }

    private function clearSqs()
    {
        $wait = 0;
        while ($wait++ < 50) {
            if (null === $message = $this->connection->get()) {
                usleep(5000);
                continue;
            }
            $this->connection->delete($message['id']);
        }
    }
}
