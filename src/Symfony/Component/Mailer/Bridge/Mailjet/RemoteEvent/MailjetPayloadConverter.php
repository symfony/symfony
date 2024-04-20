<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mailer\Bridge\Mailjet\RemoteEvent;

use Symfony\Component\RemoteEvent\Event\Mailer\AbstractMailerEvent;
use Symfony\Component\RemoteEvent\Event\Mailer\MailerDeliveryEvent;
use Symfony\Component\RemoteEvent\Event\Mailer\MailerEngagementEvent;
use Symfony\Component\RemoteEvent\Exception\ParseException;
use Symfony\Component\RemoteEvent\PayloadConverterInterface;

final class MailjetPayloadConverter implements PayloadConverterInterface
{
    public function convert(array $payload): AbstractMailerEvent
    {
        if (\in_array($payload['event'], ['bounce', 'sent', 'blocked'], true)) {
            $name = match ($payload['event']) {
                'bounce' => MailerDeliveryEvent::BOUNCE,
                'sent' => MailerDeliveryEvent::DELIVERED,
                'blocked' => MailerDeliveryEvent::DROPPED,
            };

            $event = new MailerDeliveryEvent($name, $payload['MessageID'], $payload);
            $event->setReason($this->getReason($payload));
        } else {
            $name = match ($payload['event']) {
                'click' => MailerEngagementEvent::CLICK,
                'open' => MailerEngagementEvent::OPEN,
                'spam' => MailerEngagementEvent::SPAM,
                'unsub' => MailerEngagementEvent::UNSUBSCRIBE,
                default => throw new ParseException(sprintf('Unsupported event "%s".', $payload['event'])),
            };
            $event = new MailerEngagementEvent($name, $payload['MessageID'], $payload);
        }

        if (!$date = \DateTimeImmutable::createFromFormat('U', $payload['time'])) {
            throw new ParseException(sprintf('Invalid date "%s".', $payload['time']));
        }

        $event->setDate($date);
        $event->setRecipientEmail($payload['email']);

        if (isset($payload['CustomID'])) {
            $event->setTags([$payload['CustomID']]);
        }

        if (isset($payload['Payload'])) {
            $event->setMetadata(['Payload' => $payload['Payload']]);
        }

        return $event;
    }

    private function getReason(array $payload): string
    {
        return $payload['smtp_reply'] ?? $payload['error_related_to'] ?? '';
    }
}
