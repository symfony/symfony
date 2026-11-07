<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mailer\Bridge\AhaSend\RemoteEvent;

use Symfony\Component\RemoteEvent\Event\Mailer\AbstractMailerEvent;
use Symfony\Component\RemoteEvent\Event\Mailer\MailerDeliveryEvent;
use Symfony\Component\RemoteEvent\Event\Mailer\MailerEngagementEvent;
use Symfony\Component\RemoteEvent\Exception\ParseException;
use Symfony\Component\RemoteEvent\PayloadConverterInterface;

final class AhaSendPayloadConverter implements PayloadConverterInterface
{
    public function convert(array $payload): AbstractMailerEvent
    {
        if (!isset($payload['data']['message_id_header'], $payload['data']['recipient'])) {
            throw new ParseException('The payload is malformed.');
        }

        // Without its angle brackets, the Message-ID header is the ID of the SentMessage, whether the email was sent through the v2 API or over SMTP.
        // "data.id" is AhaSend's own ID for the message, the "id" of the v2 send response.
        $id = $payload['data']['message_id_header'];
        if (str_starts_with($id, '<') && str_ends_with($id, '>')) {
            $id = substr($id, 1, -1);
        }

        if (\in_array($payload['type'], ['message.clicked', 'message.opened'], true)) {
            $name = match ($payload['type']) {
                'message.clicked' => MailerEngagementEvent::CLICK,
                'message.opened' => MailerEngagementEvent::OPEN,
                default => throw new ParseException(\sprintf('Unsupported event "%s".', $payload['type'])),
            };
            $event = new MailerEngagementEvent($name, $id, $payload);
        } elseif (str_starts_with($payload['type'], 'message.')) {
            $name = match ($payload['type']) {
                'message.reception' => MailerDeliveryEvent::RECEIVED,
                'message.delivered' => MailerDeliveryEvent::DELIVERED,
                'message.transient_error' => MailerDeliveryEvent::DEFERRED,
                'message.failed', 'message.bounced' => MailerDeliveryEvent::BOUNCE,
                'message.suppressed' => MailerDeliveryEvent::DROPPED,
                default => throw new ParseException(\sprintf('Unsupported event "%s".', $payload['type'])),
            };
            $event = new MailerDeliveryEvent($name, $id, $payload);
        } else {
            // suppressions and domain DNS problem webhooks. Ignore them for now.
            throw new ParseException(\sprintf('Unsupported event "%s".', $payload['type']));
        }

        // AhaSend sends timestamps with 9 decimal places for nanosecond precision,
        // truncate to 6 decimal places for microseconds.
        $truncatedTimestamp = substr($payload['timestamp'], 0, 26).'Z';
        $date = \DateTimeImmutable::createFromFormat(\DateTimeImmutable::ATOM, $payload['timestamp']) ?: \DateTimeImmutable::createFromFormat('Y-m-d\TH:i:s.uT', $truncatedTimestamp);
        if (!$date) {
            throw new ParseException(\sprintf('Invalid date "%s".', $payload['timestamp']));
        }
        $event->setDate($date);
        $event->setRecipientEmail($payload['data']['recipient']);

        return $event;
    }
}
