<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Webhook\Event;

use Symfony\Component\RemoteEvent\RemoteEvent;
use Symfony\Component\Webhook\Subscriber;
use Symfony\Contracts\EventDispatcher\Event;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * This event is dispatched after a webhook event is sent.
 *
 * Any listener can access the subscriber, the event and the response.
 */
final class WebhookSentEvent extends Event
{
    private Subscriber $subscriber;
    private RemoteEvent $event;
    private ResponseInterface $response;

    public function __construct(Subscriber $subscriber, RemoteEvent $event, ResponseInterface $response)
    {
        $this->subscriber = $subscriber;
        $this->event = $event;
        $this->response = $response;
    }

    public function getSubscriber(): Subscriber
    {
        return $this->subscriber;
    }

    public function getEvent(): RemoteEvent
    {
        return $this->event;
    }

    public function getResponse(): ResponseInterface
    {
        return $this->response;
    }
}
