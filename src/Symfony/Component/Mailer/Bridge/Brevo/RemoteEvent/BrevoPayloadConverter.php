<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mailer\Bridge\Brevo\RemoteEvent;

use Symfony\Component\RemoteEvent\Event\Mailer\AbstractMailerEvent;
use Symfony\Component\RemoteEvent\Event\Mailer\MailerDeliveryEvent;
use Symfony\Component\RemoteEvent\Event\Mailer\MailerEngagementEvent;
use Symfony\Component\RemoteEvent\Exception\ParseException;
use Symfony\Component\RemoteEvent\PayloadConverterInterface;

final class BrevoPayloadConverter implements PayloadConverterInterface
{
    public function convert(array $payload): AbstractMailerEvent
    {
        if (\in_array($payload['event'], ['request', 'deferred', 'delivered', 'soft_bounce', 'hard_bounce', 'invalid_email', 'blocked', 'error'], true)) {
            $name = match ($payload['event']) {
                'request' => MailerDeliveryEvent::RECEIVED,
                'deferred' => MailerDeliveryEvent::DEFERRED,
                'delivered' => MailerDeliveryEvent::DELIVERED,
                'soft_bounce' => MailerDeliveryEvent::BOUNCE,
                'hard_bounce' => MailerDeliveryEvent::BOUNCE,
                'invalid_email' => MailerDeliveryEvent::DROPPED,
                'blocked' => MailerDeliveryEvent::DROPPED,
                'error' => MailerDeliveryEvent::DROPPED,
            };

            $event = new MailerDeliveryEvent($name, $payload['message-id'], $payload);
        } else {
            $name = match ($payload['event']) {
                'click' => MailerEngagementEvent::CLICK,
                'unsubscribed' => MailerEngagementEvent::UNSUBSCRIBE,
                'unique_opened' => MailerEngagementEvent::OPEN,
                'opened' => MailerEngagementEvent::OPEN,
                'proxy_open' => MailerEngagementEvent::OPEN,
                'complaint' => MailerEngagementEvent::SPAM,
                default => throw new ParseException(sprintf('Unsupported event "%s".', $payload['event'])),
            };
            $event = new MailerEngagementEvent($name, $payload['message-id'], $payload);
        }

        if (!$date = \DateTimeImmutable::createFromFormat('U', $payload['ts_event'])) {
            throw new ParseException(sprintf('Invalid date "%s".', $payload['ts_event']));
        }

        if (
            \in_array($payload['event'], ['deferred', 'soft_bounce', 'hard_bounce', 'invalid_email', 'blocked', 'error'], true)
            && isset($payload['reason'])
        ) {
            $event->setReason($payload['reason']);
        }

        $event->setDate($date);
        $event->setRecipientEmail($payload['email']);

        if (isset($payload['tags'])) {
            $event->setTags($payload['tags']);
        }

        return $event;
    }
}
