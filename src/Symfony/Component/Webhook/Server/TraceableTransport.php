<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Webhook\Server;

use Symfony\Component\RemoteEvent\RemoteEvent;
use Symfony\Component\Webhook\Messenger\SendWebhookMessage;
use Symfony\Component\Webhook\Subscriber;

/**
 * Records the webhooks sent through a transport, so that tests can check them.
 */
final class TraceableTransport implements TransportInterface
{
    /**
     * @var list<SendWebhookMessage>
     */
    private array $sentWebhooks = [];

    public function __construct(
        private readonly TransportInterface $transport,
    ) {
    }

    public function send(Subscriber $subscriber, RemoteEvent $event): void
    {
        // recorded first, so that a webhook the subscriber rejects is still seen as sent
        $this->sentWebhooks[] = new SendWebhookMessage($subscriber, $event);

        $this->transport->send($subscriber, $event);
    }

    /**
     * @return list<SendWebhookMessage>
     */
    public function getSentWebhooks(): array
    {
        return $this->sentWebhooks;
    }

    public function reset(): void
    {
        $this->sentWebhooks = [];
    }
}
