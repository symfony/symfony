<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mailer\Bridge\Mailomat\RemoteEvent;

use Symfony\Component\RemoteEvent\Event\Mailer\AbstractMailerEvent;
use Symfony\Component\RemoteEvent\Event\Mailer\MailerDeliveryEvent;
use Symfony\Component\RemoteEvent\Event\Mailer\MailerEngagementEvent;
use Symfony\Component\RemoteEvent\Exception\ParseException;
use Symfony\Component\RemoteEvent\PayloadConverterInterface;

final class MailomatPayloadConverter implements PayloadConverterInterface
{
    public function convert(array $payload): AbstractMailerEvent
    {
        if (\in_array($payload['eventType'], ['accepted', 'not_accepted', 'delivered', 'failure_tmp', 'failure_perm'], true)) {
            $name = match ($payload['eventType']) {
                'accepted' => MailerDeliveryEvent::RECEIVED,
                'not_accepted' => MailerDeliveryEvent::DROPPED,
                'delivered' => MailerDeliveryEvent::DELIVERED,
                'failure_tmp' => MailerDeliveryEvent::DEFERRED,
                'failure_perm' => MailerDeliveryEvent::BOUNCE,
            };
            $event = new MailerDeliveryEvent($name, $payload['id'], $payload);
            if (isset($payload['payload']['reason'])) {
                $event->setReason($payload['payload']['reason']);
            }
        } else {
            $name = match ($payload['eventType']) {
                'opened' => MailerEngagementEvent::OPEN,
                'clicked' => MailerEngagementEvent::CLICK,
                default => throw new ParseException(sprintf('Unsupported event "%s".', $payload['eventType'])),
            };
            $event = new MailerEngagementEvent($name, $payload['id'], $payload);
        }

        if (!$date = \DateTimeImmutable::createFromFormat(\DateTimeInterface::ATOM, $payload['occurredAt'])) {
            throw new ParseException(sprintf('Invalid date "%s".', $payload['occurredAt']));
        }

        $event->setDate($date);
        $event->setRecipientEmail($payload['recipient']);

        return $event;
    }
}
