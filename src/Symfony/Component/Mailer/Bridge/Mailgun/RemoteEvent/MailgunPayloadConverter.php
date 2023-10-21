<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mailer\Bridge\Mailgun\RemoteEvent;

use Symfony\Component\RemoteEvent\Event\Mailer\AbstractMailerEvent;
use Symfony\Component\RemoteEvent\Event\Mailer\MailerDeliveryEvent;
use Symfony\Component\RemoteEvent\Event\Mailer\MailerEngagementEvent;
use Symfony\Component\RemoteEvent\Exception\ParseException;
use Symfony\Component\RemoteEvent\PayloadConverterInterface;

final class MailgunPayloadConverter implements PayloadConverterInterface
{
    private const MAILGUN_SPECIFIC_DROPPED_CODES = [
        605, // Not delivering to previously bounced address
        606, // Not delivering to unsubscribed address
        607, // Not delivering to a user who marked your messages as spam
        625, // Poor mailing list quality
    ];

    public function convert(array $payload): AbstractMailerEvent
    {
        if (\in_array($payload['event'], ['accepted', 'rejected', 'delivered', 'failed', 'blocked'], true)) {
            $name = match ($payload['event']) {
                'accepted' => MailerDeliveryEvent::RECEIVED,
                'rejected' => MailerDeliveryEvent::DROPPED,
                'delivered' => MailerDeliveryEvent::DELIVERED,
                'blocked' => MailerDeliveryEvent::DROPPED,
                'failed' => $this->matchFailedEvent($payload),
            };

            $event = new MailerDeliveryEvent($name, $payload['id'], $payload);
            // reason is only available on failed messages
            $event->setReason($this->getReason($payload));
        } else {
            $name = match ($payload['event']) {
                'clicked' => MailerEngagementEvent::CLICK,
                'unsubscribed' => MailerEngagementEvent::UNSUBSCRIBE,
                'opened' => MailerEngagementEvent::OPEN,
                'complained' => MailerEngagementEvent::SPAM,
                default => throw new ParseException(sprintf('Unsupported event "%s".', $payload['event'])),
            };
            $event = new MailerEngagementEvent($name, $payload['id'], $payload);
        }
        if (!$date = \DateTimeImmutable::createFromFormat('U.u', sprintf('%.6F', $payload['timestamp']))) {
            throw new ParseException(sprintf('Invalid date "%s".', sprintf('%.6F', $payload['timestamp'])));
        }
        $event->setDate($date);
        $event->setRecipientEmail($payload['recipient']);
        $event->setMetadata($payload['user-variables']);
        $event->setTags($payload['tags']);

        return $event;
    }

    private function matchFailedEvent(array $payload): string
    {
        if ('temporary' === $payload['severity']) {
            return MailerDeliveryEvent::DEFERRED;
        }
        if (\in_array($payload['delivery-status']['code'], self::MAILGUN_SPECIFIC_DROPPED_CODES, true)) {
            return MailerDeliveryEvent::DROPPED;
        }

        return MailerDeliveryEvent::BOUNCE;
    }

    private function getReason(array $payload): string
    {
        if ('' !== $payload['delivery-status']['description']) {
            return $payload['delivery-status']['description'];
        }
        if ('' !== $payload['delivery-status']['message']) {
            return $payload['delivery-status']['message'];
        }
        if ('' !== $payload['reason']) {
            return $payload['reason'];
        }

        return '';
    }
}
