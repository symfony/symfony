<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Asynchronous\Middleware;

use Symfony\Component\Messenger\Asynchronous\Routing\AbstractSenderLocator;
use Symfony\Component\Messenger\Asynchronous\Routing\SenderLocatorInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;
use Symfony\Component\Messenger\Stamp\ReceivedStamp;

/**
 * @author Samuel Roze <samuel.roze@gmail.com>
 * @author Tobias Schultze <http://tobion.de>
 */
class SendMessageMiddleware implements MiddlewareInterface
{
    private $senderLocator;
    private $messagesToSendAndHandleMapping;

    public function __construct(SenderLocatorInterface $senderLocator, array $messagesToSendAndHandleMapping = array())
    {
        $this->senderLocator = $senderLocator;
        $this->messagesToSendAndHandleMapping = $messagesToSendAndHandleMapping;
    }

    /**
     * {@inheritdoc}
     */
    public function handle(Envelope $envelope, callable $next): void
    {
        if ($envelope->get(ReceivedStamp::class)) {
            // It's a received message. Do not send it back:
            $next($envelope);

            return;
        }

        $sender = $this->senderLocator->getSender($envelope);

        if ($sender) {
            $sender->send($envelope);

            if (!AbstractSenderLocator::getValueFromMessageRouting($this->messagesToSendAndHandleMapping, $envelope)) {
                // message has no corresponding handler
                return;
            }
        }

        $next($envelope);
    }
}
