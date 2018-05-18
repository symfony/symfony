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

use Symfony\Component\Messenger\Asynchronous\Routing\SenderLocatorInterface;
use Symfony\Component\Messenger\Asynchronous\Transport\ReceivedMessage;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\EnvelopeAwareInterface;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;

/**
 * @author Samuel Roze <samuel.roze@gmail.com>
 */
class SendMessageMiddleware implements MiddlewareInterface, EnvelopeAwareInterface
{
    private $senderLocator;

    public function __construct(SenderLocatorInterface $senderLocator)
    {
        $this->senderLocator = $senderLocator;
    }

    /**
     * {@inheritdoc}
     */
    public function handle($message, callable $next)
    {
        $envelope = Envelope::wrap($message);
        if ($envelope->get(ReceivedMessage::class)) {
            // It's a received message. Do not send it back:
            return $next($message);
        }

        if (!empty($senders = $this->senderLocator->getSendersForMessage($envelope->getMessage()))) {
            foreach ($senders as $sender) {
                if (null === $sender) {
                    continue;
                }

                $sender->send($envelope);
            }

            if (!\in_array(null, $senders, true)) {
                return;
            }
        }

        return $next($message);
    }
}
