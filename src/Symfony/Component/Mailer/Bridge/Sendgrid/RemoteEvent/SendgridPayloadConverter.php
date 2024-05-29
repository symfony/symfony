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

/**
 * @author WoutervanderLoop.nl <info@woutervanderloop.nl>
 */
final class SendgridPayloadConverter implements PayloadConverterInterface
{
    public function convert(array $payload): AbstractMailerEvent
    {
        if (\in_array($payload['event'], ['processed', 'delivered', 'bounce', 'dropped', 'deferred'], true)) {
            $name = match ($payload['event']) {
                'processed', 'delivered' => MailerDeliveryEvent::DELIVERED,
                'dropped' => MailerDeliveryEvent::DROPPED,
                'deferred' => MailerDeliveryEvent::DEFERRED,
                'bounce' => MailerDeliveryEvent::BOUNCE,
            };
            $event = new MailerDeliveryEvent($name, $payload['sg_message_id'], $payload);
            $event->setReason($payload['reason'] ?? '');
        } else {
            $name = match ($payload['event']) {
                'click' => MailerEngagementEvent::CLICK,
                'unsubscribe' => MailerEngagementEvent::UNSUBSCRIBE,
                'open' => MailerEngagementEvent::OPEN,
                'spamreport' => MailerEngagementEvent::SPAM,
                default => throw new ParseException(sprintf('Unsupported event "%s".', $payload['unsubscribe'])),
            };
            $event = new MailerEngagementEvent($name, $payload['sg_message_id'], $payload);
        }

        if (!$date = \DateTimeImmutable::createFromFormat('U', $payload['timestamp'])) {
            throw new ParseException(sprintf('Invalid date "%s".', $payload['timestamp']));
        }

        $event->setDate($date);
        $event->setRecipientEmail($payload['email']);
        $event->setMetadata([]);
        $event->setTags($payload['category'] ?? []);

        return $event;
    }
}
