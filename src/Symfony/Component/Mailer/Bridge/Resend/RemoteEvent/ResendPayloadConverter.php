<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mailer\Bridge\Resend\RemoteEvent;

use Symfony\Component\RemoteEvent\Event\Mailer\AbstractMailerEvent;
use Symfony\Component\RemoteEvent\Event\Mailer\MailerDeliveryEvent;
use Symfony\Component\RemoteEvent\Event\Mailer\MailerEngagementEvent;
use Symfony\Component\RemoteEvent\Exception\ParseException;
use Symfony\Component\RemoteEvent\PayloadConverterInterface;

final class ResendPayloadConverter implements PayloadConverterInterface
{
    public function convert(array $payload): AbstractMailerEvent
    {
        if (\in_array($payload['type'], ['email.sent', 'email.delivered', 'email.delivery_delayed', 'email.bounced'], true)) {
            $name = match ($payload['type']) {
                'email.sent' => MailerDeliveryEvent::RECEIVED,
                'email.delivered' => MailerDeliveryEvent::DELIVERED,
                'email.delivery_delayed' => MailerDeliveryEvent::DEFERRED,
                'email.bounced' => MailerDeliveryEvent::BOUNCE,
            };

            $event = new MailerDeliveryEvent($name, $payload['data']['email_id'], $payload);
        } else {
            $name = match ($payload['type']) {
                'email.clicked' => MailerEngagementEvent::CLICK,
                'email.opened' => MailerEngagementEvent::OPEN,
                'email.complained' => MailerEngagementEvent::SPAM,
                default => throw new ParseException(\sprintf('Unsupported event "%s".', $payload['type'])),
            };
            $event = new MailerEngagementEvent($name, $payload['data']['email_id'], $payload);
        }

        if (!$date = \DateTimeImmutable::createFromFormat('Y-m-d\TH:i:s.uP', $payload['created_at'])) {
            throw new ParseException(\sprintf('Invalid date "%s".', $payload['created_at']));
        }

        $event->setDate($date);
        $event->setRecipientEmail(implode(', ', $payload['data']['to']));
        $event->setMetadata($payload['data']);

        return $event;
    }
}
