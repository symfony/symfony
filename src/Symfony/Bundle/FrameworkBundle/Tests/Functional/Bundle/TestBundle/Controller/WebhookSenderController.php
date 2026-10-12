<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Tests\Functional\Bundle\TestBundle\Controller;

use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\RemoteEvent\RemoteEvent;
use Symfony\Component\Webhook\Messenger\SendWebhookMessage;
use Symfony\Component\Webhook\Server\TransportInterface;
use Symfony\Component\Webhook\Subscriber;

class WebhookSenderController
{
    public function __construct(
        private TransportInterface $transport,
        private MessageBusInterface $bus,
    ) {
    }

    public function send(string $event): Response
    {
        $this->transport->send(new Subscriber('https://example.com/webhook', 'the-secret'), new RemoteEvent($event, 'order-42', ['order' => 42]));

        return new RedirectResponse('/webhook-sender/sent');
    }

    public function dispatch(string $event): Response
    {
        $this->bus->dispatch(new SendWebhookMessage(new Subscriber('https://example.com/webhook', 'the-secret'), new RemoteEvent($event, 'order-42', ['order' => 42])));

        return new Response('', 202);
    }

    public function sent(): Response
    {
        return new Response('');
    }
}
