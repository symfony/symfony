<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mailer\Bridge\Sendgrid\RemoteEvent;

use Symfony\Component\RemoteEvent\Event\Mailer\AbstractMailerEvent;
use Symfony\Component\RemoteEvent\Event\Mailer\MailerDeliveryEvent;
use Symfony\Component\RemoteEvent\Event\Mailer\MailerEngagementEvent;
use Symfony\Component\RemoteEvent\Exception\ParseException;
use Symfony\Component\RemoteEvent\PayloadConverterInterface;

final class SendgridPayloadConverter implements PayloadConverterInterface
{
    public function convert(array $payload): AbstractMailerEvent
    {
        if (\in_array($payload['event'], ['bounce', 'deferred', 'delivered', 'dropped', 'processed'], true)) {
            $name = match ($payload['event']) {
                'bounce' => MailerDeliveryEvent::BOUNCE,
                'deferred' => MailerDeliveryEvent::DEFERRED,
                'delivered' => MailerDeliveryEvent::DELIVERED,
                'dropped' => MailerDeliveryEvent::DROPPED,
                'processed' => MailerDeliveryEvent::RECEIVED,
            };
            $event = new MailerDeliveryEvent($name, $payload['sg_event_id'], $payload);
            if (isset($payload['reason'])) {
                $event->setReason($payload['reason']);
            }
        } else {
            $name = match ($payload['event']) {
                'click' => MailerEngagementEvent::CLICK,
                'group_unsubscribe' => MailerEngagementEvent::UNSUBSCRIBE,
                'open' => MailerEngagementEvent::OPEN,
                'spamreport' => MailerEngagementEvent::SPAM,
                'unsubscribe' => MailerEngagementEvent::UNSUBSCRIBE,
                default => throw new ParseException(sprintf('Unsupported event "%s".', $payload['event'])),
            };
            $event = new MailerEngagementEvent($name, $payload['sg_event_id'], $payload);
        }

        if (!$date = \DateTimeImmutable::createFromFormat('U', $payload['timestamp'])) {
            throw new ParseException(sprintf('Invalid date "%s".', $payload['timestamp']));
        }
        $event->setDate($date);

        $event->setRecipientEmail($payload['email']);

        if (isset($payload['category'])) {
            $event->setTags((array) $payload['category']);
        }

        return $event;
    }
}
