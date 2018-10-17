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
use Symfony\Component\Messenger\Asynchronous\Transport\ReceivedMessage;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\EnvelopeAwareInterface;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;

/**
 * @author Samuel Roze <samuel.roze@gmail.com>
 * @author Tobias Schultze <http://tobion.de>
 */
class SendMessageMiddleware implements MiddlewareInterface, EnvelopeAwareInterface
{
    private $senderLocator;
    private $messagesToSendAndHandleMapping;

    public function __construct(SenderLocatorInterface $senderLocator, array $messagesToSendAndHandleMapping = array())
    {
        $this->senderLocator = $senderLocator;
        $this->messagesToSendAndHandleMapping = $messagesToSendAndHandleMapping;
    }

    /**
     * @param Envelope $envelope
     *
     * {@inheritdoc}
     */
    public function handle($envelope, callable $next): void
    {
        if ($envelope->get(ReceivedMessage::class)) {
            // It's a received message. Do not send it back:
            $next($envelope);

            return;
        }

        $sender = $this->senderLocator->getSenderForMessage($envelope->getMessage());

        if ($sender) {
            $sender->send($envelope);

            if (!$this->mustSendAndHandle($envelope->getMessage())) {
                return;
            }
        }

        $next($envelope);
    }

    private function mustSendAndHandle($message): bool
    {
        return (bool) AbstractSenderLocator::getValueFromMessageRouting($this->messagesToSendAndHandleMapping, $message);
    }
}
