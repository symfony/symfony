<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mailer\Bridge\Scaleway\RemoteEvent;

use Symfony\Component\RemoteEvent\Event\Mailer\AbstractMailerEvent;
use Symfony\Component\RemoteEvent\Event\Mailer\MailerDeliveryEvent;
use Symfony\Component\RemoteEvent\Event\Mailer\MailerEngagementEvent;
use Symfony\Component\RemoteEvent\Exception\ParseException;
use Symfony\Component\RemoteEvent\PayloadConverterInterface;

final class ScalewayPayloadConverter implements PayloadConverterInterface
{
    public function convert(array $payload): AbstractMailerEvent
    {
        $id = $payload['email_id'] ?? $payload['id'] ?? throw new ParseException('Missing event identifier.');

        if ('email_spam' === $payload['type']) {
            $event = new MailerEngagementEvent(MailerEngagementEvent::SPAM, $id, $payload);
        } else {
            $name = match ($payload['type']) {
                'email_queued' => MailerDeliveryEvent::RECEIVED,
                'email_delivered' => MailerDeliveryEvent::DELIVERED,
                'email_deferred' => MailerDeliveryEvent::DEFERRED,
                'email_dropped', 'email_mailbox_not_found' => MailerDeliveryEvent::BOUNCE,
                'email_blocklisted' => MailerDeliveryEvent::DROPPED,
                default => throw new ParseException(\sprintf('Unsupported event "%s".', $payload['type'])),
            };

            $event = new MailerDeliveryEvent($name, $id, $payload);

            if (!\in_array($name, [MailerDeliveryEvent::RECEIVED, MailerDeliveryEvent::DELIVERED], true)) {
                $event->setReason($payload['blocklist_reason'] ?? $payload['email_response_message'] ?? $payload['email_error'] ?? '');
            }
        }

        if (!$createdAt = $payload['created_at'] ?? null) {
            throw new ParseException('Missing "created_at" field.');
        }

        try {
            $event->setDate(new \DateTimeImmutable($createdAt));
        } catch (\Exception) {
            throw new ParseException(\sprintf('Invalid date "%s".', $createdAt));
        }

        if (isset($payload['email_to'])) {
            $event->setRecipientEmail($payload['email_to']);
        }

        return $event;
    }
}
