<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mailer\Bridge\Mailtrap\RemoteEvent;

use Symfony\Component\RemoteEvent\Event\Mailer\MailerDeliveryEvent;
use Symfony\Component\RemoteEvent\Event\Mailer\MailerEngagementEvent;
use Symfony\Component\RemoteEvent\Exception\ParseException;
use Symfony\Component\RemoteEvent\PayloadConverterInterface;
use Symfony\Component\RemoteEvent\RemoteEvent;

/**
 * @author Kevin Bond <kevinbond@gmail.com>
 */
final class MailtrapPayloadConverter implements PayloadConverterInterface
{
    public function convert(array $payload): RemoteEvent
    {
        $type = match ($payload['event']) {
            'delivery' => MailerDeliveryEvent::DELIVERED,
            'open' => MailerEngagementEvent::OPEN,
            'click' => MailerEngagementEvent::CLICK,
            'unsubscribe' => MailerEngagementEvent::UNSUBSCRIBE,
            'spam' => MailerEngagementEvent::SPAM,
            'soft bounce', 'bounce' => MailerDeliveryEvent::BOUNCE,
            'suspension', 'reject' => MailerDeliveryEvent::DROPPED,
            default => throw new ParseException(\sprintf('Unsupported event "%s".', $payload['event'])),
        };

        if (\in_array($type, [MailerDeliveryEvent::DELIVERED, MailerDeliveryEvent::BOUNCE, MailerDeliveryEvent::DROPPED], true)) {
            $event = new MailerDeliveryEvent($type, $payload['message_id'], $payload);
            $event->setReason($payload['reason'] ?? $payload['response'] ?? '');
        } else {
            $event = new MailerEngagementEvent($type, $payload['message_id'], $payload);
        }

        if (!$date = \DateTimeImmutable::createFromFormat('U', $payload['timestamp'])) {
            throw new ParseException(\sprintf('Invalid date "%s".', $payload['timestamp']));
        }

        $event->setDate($date);
        $event->setRecipientEmail($payload['email']);

        if (isset($payload['category'])) {
            $event->setTags([$payload['category']]);
        }

        if (isset($payload['custom_variables'])) {
            $event->setMetadata($payload['custom_variables']);
        }

        return $event;
    }
}
