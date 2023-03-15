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

use Symfony\Component\Webhook\Server\TransportInterface;

/**
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @experimental in 6.3
 */
class SendWebhookHandler
{
    public function __construct(
        private readonly TransportInterface $transport,
    ) {
    }

    public function __invoke(SendWebhookMessage $message): void
    {
        $this->transport->send($message->getSubscriber(), $message->getEvent());
    }
}
