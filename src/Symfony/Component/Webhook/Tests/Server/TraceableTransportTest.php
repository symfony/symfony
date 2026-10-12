<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Webhook\Tests\Server;

use PHPUnit\Framework\TestCase;
use Symfony\Component\RemoteEvent\RemoteEvent;
use Symfony\Component\Webhook\Messenger\SendWebhookMessage;
use Symfony\Component\Webhook\Server\TraceableTransport;
use Symfony\Component\Webhook\Server\TransportInterface;
use Symfony\Component\Webhook\Subscriber;

class TraceableTransportTest extends TestCase
{
    public function testSentWebhooksAreRecorded()
    {
        $inner = new RecordingTransport();
        $transport = new TraceableTransport($inner);
        $subscriber = new Subscriber('https://example.com/webhook', 's3cr3t');
        $event = new RemoteEvent('order.paid', 'order-42', ['order' => 42]);

        $transport->send($subscriber, $event);

        $this->assertEquals([new SendWebhookMessage($subscriber, $event)], $transport->getSentWebhooks());
        $this->assertSame([[$subscriber, $event]], $inner->sent);
    }

    public function testWebhooksTheInnerTransportFailsToSendAreRecorded()
    {
        $transport = new TraceableTransport(new RecordingTransport(new \RuntimeException('Connection refused.')));

        try {
            $transport->send(new Subscriber('https://example.com/webhook', 's3cr3t'), new RemoteEvent('order.paid', 'order-42', []));
            $this->fail('The failure of the inner transport is swallowed.');
        } catch (\RuntimeException $e) {
            $this->assertSame('Connection refused.', $e->getMessage());
        }

        $this->assertCount(1, $transport->getSentWebhooks());
    }

    public function testResetForgetsTheSentWebhooks()
    {
        $transport = new TraceableTransport(new RecordingTransport());
        $transport->send(new Subscriber('https://example.com/webhook', 's3cr3t'), new RemoteEvent('order.paid', 'order-42', []));

        $transport->reset();

        $this->assertSame([], $transport->getSentWebhooks());
    }
}

class RecordingTransport implements TransportInterface
{
    public array $sent = [];

    public function __construct(
        private ?\Throwable $failure = null,
    ) {
    }

    public function send(Subscriber $subscriber, RemoteEvent $event): void
    {
        if ($this->failure) {
            throw $this->failure;
        }

        $this->sent[] = [$subscriber, $event];
    }
}
