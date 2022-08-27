<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Webhook\Messenger;

use Symfony\Component\RemoteEvent\RemoteEvent;
use Symfony\Component\Webhook\Subscriber;

/**
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @experimental in 6.3
 */
class SendWebhookMessage
{
    public function __construct(
        private readonly Subscriber $subscriber,
        private readonly RemoteEvent $event,
    ) {
    }

    public function getSubscriber(): Subscriber
    {
        return $this->subscriber;
    }

    public function getEvent(): RemoteEvent
    {
        return $this->event;
    }
}
