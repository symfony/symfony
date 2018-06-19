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

use Symfony\Component\Messenger\Asynchronous\Routing\SenderLocator;
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
     * {@inheritdoc}
     */
    public function handle($message, callable $next)
    {
        $envelope = Envelope::wrap($message);
        if ($envelope->get(ReceivedMessage::class)) {
            // It's a received message. Do not send it back:
            return $next($message);
        }

        $sender = $this->senderLocator->getSenderForMessage($envelope->getMessage());

        if ($sender) {
            $sender->send($envelope);

            if (!$this->mustSendAndHandle($envelope->getMessage())) {
                return;
            }
        }

        return $next($message);
    }

    private function mustSendAndHandle($message): bool
    {
        return (bool) SenderLocator::getValueFromMessageRouting($this->messagesToSendAndHandleMapping, $message);
    }
}
