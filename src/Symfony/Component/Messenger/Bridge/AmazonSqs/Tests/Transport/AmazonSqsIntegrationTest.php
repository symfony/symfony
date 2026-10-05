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
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Bridge\AmazonSqs\Tests\Fixtures\DummyMessage;
use Symfony\Component\Messenger\Bridge\AmazonSqs\Transport\Connection;

#[Group('integration')]
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

    public function testConnectionSendBatchAndGet()
    {
        if (!getenv('MESSENGER_SQS_DSN')) {
            $this->markTestSkipped('The "MESSENGER_SQS_DSN" environment variable is required.');
        }

        $this->executeBatch(getenv('MESSENGER_SQS_DSN'));
    }

    public function testConnectionSendBatchToFifoQueueAndGet()
    {
        if (!getenv('MESSENGER_SQS_FIFO_QUEUE_DSN')) {
            $this->markTestSkipped('The "MESSENGER_SQS_FIFO_QUEUE_DSN" environment variable is required.');
        }

        $this->executeBatch(getenv('MESSENGER_SQS_FIFO_QUEUE_DSN'));
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

        $this->assertEquals('{"message": "Hi"}', $encoded[0]['body']);
        $this->assertEquals(['type' => DummyMessage::class, DummyMessage::class => 'special'], $encoded[0]['headers']);

        $this->waitUntilElapsed(seconds: 1.0, since: $messageSentAt);
        $connection->keepalive($encoded[0]['id']);
        $this->waitUntilElapsed(seconds: 2.0, since: $messageSentAt);
        $this->assertSame(0, $connection->getMessageCount(), 'The queue should be empty since visibility timeout was extended');
        $connection->delete($encoded[0]['id']);
    }

    private function executeBatch(string $dsn): void
    {
        $connection = Connection::fromDsn($dsn);
        $connection->setup();
        $this->clearSqs($dsn);

        $messages = [];
        for ($i = 0; $i < 12; ++$i) {
            $messages[] = ['{"message": "Hi '.$i.'"}', ['type' => DummyMessage::class, DummyMessage::class => 'special']];
        }

        $this->assertSame([], $connection->sendBatch($messages));
        $this->assertSame(12, $connection->getMessageCount());

        $bodies = [];
        $wait = 0;
        while (\count($bodies) < 12 && $wait++ < 200) {
            foreach ($connection->get() ?? [] as $encoded) {
                $this->assertEquals(['type' => DummyMessage::class, DummyMessage::class => 'special'], $encoded['headers']);
                $bodies[] = $encoded['body'];
                $connection->delete($encoded['id']);
            }
        }

        if (!str_ends_with($dsn, '.fifo') && !str_contains($dsn, '.fifo?')) {
            sort($bodies, \SORT_NATURAL);
        }

        $this->assertSame(array_column($messages, 0), $bodies);
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
