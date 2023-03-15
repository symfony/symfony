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
    public function convert(array $payload): AbstractMailerEvent
    {
        if (\in_array($payload['event'], ['accepted', 'rejected', 'delivered', 'failed', 'blocked'], true)) {
            $name = match ($payload['event']) {
                'accepted' => MailerDeliveryEvent::RECEIVED,
                'rejected' => MailerDeliveryEvent::DROPPED,
                'delivered' => MailerDeliveryEvent::DELIVERED,
                'blocked' => MailerDeliveryEvent::DROPPED,
                'failed' => 'permanent' === $payload['severity'] ? MailerDeliveryEvent::BOUNCE : MailerDeliveryEvent::DEFERRED,
            };

            $event = new MailerDeliveryEvent($name, $payload['id'], $payload);
            // reason is only available on failed messages
            $event->setReason($payload['reason'] ?? '');
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
        if (!$date = \DateTimeImmutable::createFromFormat('U.u', $payload['timestamp'])) {
            throw new ParseException(sprintf('Invalid date "%s".', $payload['timestamp']));
        }
        $event->setDate($date);
        $event->setRecipientEmail($payload['recipient']);
        $event->setMetadata($payload['user-variables']);
        $event->setTags($payload['tags']);

        return $event;
    }
}
