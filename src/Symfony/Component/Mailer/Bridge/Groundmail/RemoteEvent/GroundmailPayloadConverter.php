<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mailer\Bridge\Groundmail\RemoteEvent;

use Symfony\Component\RemoteEvent\Event\Mailer\AbstractMailerEvent;
use Symfony\Component\RemoteEvent\Event\Mailer\MailerDeliveryEvent;
use Symfony\Component\RemoteEvent\Event\Mailer\MailerEngagementEvent;
use Symfony\Component\RemoteEvent\Exception\ParseException;
use Symfony\Component\RemoteEvent\PayloadConverterInterface;

/**
 * @see https://docs.groundmail.dev/api/webhooks#supported-events
 *
 * @author Shivansh Mehendiratta <shivansh@backslope.studio>
 */
final class GroundmailPayloadConverter implements PayloadConverterInterface
{
    private const DELIVERY_EVENTS = [
        'email.sent' => MailerDeliveryEvent::RECEIVED,
        'email.delivered' => MailerDeliveryEvent::DELIVERED,
        'email.delivery_delayed' => MailerDeliveryEvent::DEFERRED,
        // held by content scanning or while sending is paused, then sent or failed
        'email.held' => MailerDeliveryEvent::DEFERRED,
        'email.bounced' => MailerDeliveryEvent::BOUNCE,
        'email.failed' => MailerDeliveryEvent::DROPPED,
        'email.suppressed' => MailerDeliveryEvent::DROPPED,
    ];

    private const ENGAGEMENT_EVENTS = [
        'email.opened' => MailerEngagementEvent::OPEN,
        'email.clicked' => MailerEngagementEvent::CLICK,
        'email.complained' => MailerEngagementEvent::SPAM,
        'contact.unsubscribed' => MailerEngagementEvent::UNSUBSCRIBE,
    ];

    public function convert(array $payload): AbstractMailerEvent
    {
        $type = $payload['type'];
        $data = $payload['data'];

        if (!isset(self::DELIVERY_EVENTS[$type]) && !isset(self::ENGAGEMENT_EVENTS[$type])) {
            throw new ParseException(\sprintf('Unsupported event "%s".', $type));
        }

        if ('contact.unsubscribed' === $type) {
            // the email that carried the unsubscribe link is not always known
            $id = $data['email_id'] ?? $data['suppression_id'] ?? $data['category_opt_out_id'] ?? null;
            $recipient = $data['email'] ?? null;
        } else {
            $id = $data['email_id'] ?? null;
            $recipient = isset($data['to']) ? implode(', ', (array) $data['to']) : null;
        }

        if (!\is_string($id) || !\is_string($recipient)) {
            throw new ParseException(\sprintf('The "%s" event has no email id or recipient.', $type));
        }

        if (isset(self::DELIVERY_EVENTS[$type])) {
            $event = new MailerDeliveryEvent(self::DELIVERY_EVENTS[$type], $id, $payload);
            $event->setReason((string) ($data['bounce_reason'] ?? $data['held_reason'] ?? ''));
        } else {
            $event = new MailerEngagementEvent(self::ENGAGEMENT_EVENTS[$type], $id, $payload);
        }

        if (!$date = \DateTimeImmutable::createFromFormat('Y-m-d\TH:i:s.uP', $payload['created_at'])) {
            throw new ParseException(\sprintf('Invalid date "%s".', $payload['created_at']));
        }

        $event->setDate($date);
        $event->setRecipientEmail($recipient);

        // the tags of the email, as sent: the transports send the metadata of a message as tags
        $metadata = [];
        foreach ($data['tags'] ?? [] as $tag) {
            if (isset($tag['name'], $tag['value'])) {
                $metadata[$tag['name']] = $tag['value'];
            }
        }
        $event->setMetadata($metadata);

        return $event;
    }
}
