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

use AsyncAws\Sqs\SqsClient;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Bridge\AmazonSqs\Tests\Fixtures\DummyMessage;
use Symfony\Component\Messenger\Bridge\AmazonSqs\Transport\Connection;

/**
 * @group integration
 */
class AmazonSqsIntegrationTest extends TestCase
{
    public function testConnectionSendToFifoQueueAndGet()
    {
        if (!getenv('MESSENGER_SQS_FIFO_QUEUE_DSN')) {
            $this->markTestSkipped('The "MESSENGER_SQS_FIFO_QUEUE_DSN" environment variable is required.');
        }

        $this->execute(getenv('MESSENGER_SQS_FIFO_QUEUE_DSN'));
    }

    public function testConnectionSendAndGet()
    {
        if (!getenv('MESSENGER_SQS_DSN')) {
            $this->markTestSkipped('The "MESSENGER_SQS_DSN" environment variable is required.');
        }

        $this->execute(getenv('MESSENGER_SQS_DSN'));
    }

    private function execute(string $dsn): void
    {
        $connection = Connection::fromDsn($dsn, ['visibility_timeout' => 1]);
        $connection->setup();
        $this->clearSqs($dsn);

        $connection->send('{"message": "Hi"}', ['type' => DummyMessage::class, DummyMessage::class => 'special']);
        $messageSentAt = microtime(true);
        $this->assertSame(1, $connection->getMessageCount());

        $wait = 0;
        while ((null === $encoded = $connection->get()) && $wait++ < 200) {
            usleep(5000);
        }

        $this->assertEquals('{"message": "Hi"}', $encoded['body']);
        $this->assertEquals(['type' => DummyMessage::class, DummyMessage::class => 'special'], $encoded['headers']);

        $this->waitUntilElapsed(seconds: 1.0, since: $messageSentAt);
        $connection->keepalive($encoded['id']);
        $this->waitUntilElapsed(seconds: 2.0, since: $messageSentAt);
        $this->assertSame(0, $connection->getMessageCount(), 'The queue should be empty since visibility timeout was extended');
        $connection->delete($encoded['id']);
    }

    private function waitUntilElapsed(float $seconds, float $since): void
    {
        $waitTime = $seconds - (microtime(true) - $since);
        if ($waitTime > 0) {
            usleep((int) ($waitTime * 1e6));
        }
    }

    private function clearSqs(string $dsn): void
    {
        $url = parse_url($dsn);
        $client = new SqsClient(['endpoint' => "http://{$url['host']}:{$url['port']}"]);
        $client->purgeQueue([
            'QueueUrl' => $client->getQueueUrl(['QueueName' => ltrim($url['path'], '/')])->getQueueUrl(),
        ]);
    }
}
