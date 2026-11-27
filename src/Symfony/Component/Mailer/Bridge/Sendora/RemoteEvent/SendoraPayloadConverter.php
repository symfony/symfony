<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mailer\Bridge\Sendora\RemoteEvent;

use Symfony\Component\RemoteEvent\Event\Mailer\MailerDeliveryEvent;
use Symfony\Component\RemoteEvent\Event\Mailer\MailerEngagementEvent;
use Symfony\Component\RemoteEvent\Exception\ParseException;
use Symfony\Component\RemoteEvent\PayloadConverterInterface;
use Symfony\Component\RemoteEvent\RemoteEvent;

/**
 * @see https://sendora.se/docs/api/webhook-events
 */
final class SendoraPayloadConverter implements PayloadConverterInterface
{
    private const DELIVERY_EVENTS = [
        'delivered' => MailerDeliveryEvent::DELIVERED,
        'bounced' => MailerDeliveryEvent::BOUNCE,
        'deferred' => MailerDeliveryEvent::DEFERRED,
    ];

    private const ENGAGEMENT_EVENTS = [
        'spam_complaint' => MailerEngagementEvent::SPAM,
        'unsubscribed' => MailerEngagementEvent::UNSUBSCRIBE,
        'opened' => MailerEngagementEvent::OPEN,
        'clicked' => MailerEngagementEvent::CLICK,
    ];

    public function convert(array $payload): RemoteEvent
    {
        $type = $payload['event'];

        // Symfony has no mailer event for the others, such as received mail or the monthly cap, and the webhook
        // controller answers 406 to a parser that returns nothing, which Sendora would retry for a day
        if (!isset(self::DELIVERY_EVENTS[$type]) && !isset(self::ENGAGEMENT_EVENTS[$type])) {
            return new RemoteEvent($type, $payload['id'], $payload);
        }

        if (!\is_string($payload['messageId'] ?? null) || !\is_string($payload['recipient'] ?? null) || !\is_string($payload['occurredAt'] ?? null)) {
            throw new ParseException(\sprintf('The payload of the "%s" event is malformed.', $type));
        }

        if (isset(self::DELIVERY_EVENTS[$type])) {
            $event = new MailerDeliveryEvent(self::DELIVERY_EVENTS[$type], $payload['messageId'], $payload);
            if ('delivered' !== $type) {
                // a bounce that the receiving server gave no response for still has a classification
                $event->setReason($payload['details']['response'] ?? $payload['details']['classification'] ?? '');
            }
        } else {
            $event = new MailerEngagementEvent(self::ENGAGEMENT_EVENTS[$type], $payload['messageId'], $payload);
        }

        if (!$date = \DateTimeImmutable::createFromFormat('Y-m-d\TH:i:s.uP', $payload['occurredAt'])) {
            throw new ParseException(\sprintf('Invalid date "%s".', $payload['occurredAt']));
        }

        $event->setDate($date);
        $event->setRecipientEmail($payload['recipient']);
        $event->setMetadata($payload['metadata'] ?? []);
        $event->setTags(isset($payload['tag']) ? [$payload['tag']] : []);

        return $event;
    }
}
