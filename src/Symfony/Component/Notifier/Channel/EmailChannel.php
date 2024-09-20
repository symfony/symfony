<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Channel;

use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\Messenger\SendEmailMessage;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\Notifier\Exception\LogicException;
use Symfony\Component\Notifier\Message\EmailMessage;
use Symfony\Component\Notifier\Notification\EmailNotificationInterface;
use Symfony\Component\Notifier\Notification\Notification;
use Symfony\Component\Notifier\Recipient\EmailRecipientInterface;
use Symfony\Component\Notifier\Recipient\RecipientInterface;

/**
 * @author Fabien Potencier <fabien@symfony.com>
 */
class EmailChannel implements ChannelInterface
{
    private string|Address|null $from;

    public function __construct(
        private ?TransportInterface $transport = null,
        private ?MessageBusInterface $bus = null,
        ?string $from = null,
        private ?Envelope $envelope = null,
    ) {
        if (null === $transport && null === $bus) {
            throw new LogicException(\sprintf('"%s" needs a Transport or a Bus but both cannot be "null".', static::class));
        }

        $this->from = $from ?: $envelope?->getSender();
    }

    /**
     * @param EmailRecipientInterface $recipient
     */
    public function notify(Notification $notification, RecipientInterface $recipient, ?string $transportName = null): void
    {
        $message = null;
        if ($notification instanceof EmailNotificationInterface) {
            $message = $notification->asEmailMessage($recipient, $transportName);
        }

        $message ??= EmailMessage::fromNotification($notification, $recipient, $transportName);
        $email = $message->getMessage();
        if ($email instanceof Email) {
            if (!$email->getFrom()) {
                if (null === $this->from) {
                    throw new LogicException(\sprintf('To send the "%s" notification by email, you must configure a "from" header by either setting a sender in the global "envelope" of the mailer configuration or by setting a "from" header in the "asEmailMessage()" method.', get_debug_type($notification)));
                }

                $email->from($this->from);
            }

            if (!$email->getTo()) {
                $email->to($recipient->getEmail());
            }
        }

        if (null !== $this->envelope) {
            $message->envelope($this->envelope);
        }

        if (null !== $transportName) {
            $message->transport($transportName);
        }

        if (null === $this->bus) {
            $this->transport->send($message->getMessage(), $message->getEnvelope());
        } else {
            $this->bus->dispatch(new SendEmailMessage($message->getMessage(), $message->getEnvelope()));
        }
    }

    public function supports(Notification $notification, RecipientInterface $recipient): bool
    {
        return $recipient instanceof EmailRecipientInterface;
    }
}
