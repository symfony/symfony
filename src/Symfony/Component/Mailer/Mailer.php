<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mailer;

use Symfony\Component\Mailer\Event\MessageEvent;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\Messenger\SendEmailMessage;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Mime\RawMessage;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @author Fabien Potencier <fabien@symfony.com>
 */
class Mailer implements MailerInterface
{
    private $transport;
    private $bus;
    private $dispatcher;

    public function __construct(TransportInterface $transport, MessageBusInterface $bus = null, EventDispatcherInterface $dispatcher = null)
    {
        $this->transport = $transport;
        $this->bus = $bus;
        $this->dispatcher = $dispatcher;
    }

    public function send(RawMessage $message, SmtpEnvelope $envelope = null): void
    {
        if (null === $this->bus) {
            $this->transport->send($message, $envelope);

            return;
        }

        if (null !== $this->dispatcher) {
            $message = clone $message;
            if (null !== $envelope) {
                $envelope = clone $envelope;
            } else {
                try {
                    $envelope = new DelayedSmtpEnvelope($message);
                } catch (\Exception $e) {
                    throw new TransportException('Cannot send message without a valid envelope.', 0, $e);
                }
            }
            $event = new MessageEvent($message, $envelope, $this->transport->getName(), true);
            $this->dispatcher->dispatch($event);
        }

        $this->bus->dispatch(new SendEmailMessage($message, $envelope));
    }
}
