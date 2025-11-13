<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mailer\Bridge\Mailchimp\RemoteEvent;

use Symfony\Component\RemoteEvent\Event\Mailer\AbstractMailerEvent;
use Symfony\Component\RemoteEvent\Event\Mailer\MailerDeliveryEvent;
use Symfony\Component\RemoteEvent\Event\Mailer\MailerEngagementEvent;
use Symfony\Component\RemoteEvent\Exception\ParseException;
use Symfony\Component\RemoteEvent\PayloadConverterInterface;

final class MailchimpPayloadConverter implements PayloadConverterInterface
{
    public function convert(array $payload): AbstractMailerEvent
    {
        if (\in_array($payload['event'], ['send', 'deferral', 'soft_bounce', 'hard_bounce', 'delivered', 'reject'], true)) {
            $name = match ($payload['event']) {
                'send' => MailerDeliveryEvent::RECEIVED,
                'deferral', => MailerDeliveryEvent::DEFERRED,
                'soft_bounce', 'hard_bounce' => MailerDeliveryEvent::BOUNCE,
                'delivered' => MailerDeliveryEvent::DELIVERED,
                'reject' => MailerDeliveryEvent::DROPPED,
            };

            $event = new MailerDeliveryEvent($name, $payload['msg']['_id'], $payload);
            // reason is only available on failed messages
            $event->setReason($this->getReason($payload));
        } else {
            $name = match ($payload['event']) {
                'click' => MailerEngagementEvent::CLICK,
                'open' => MailerEngagementEvent::OPEN,
                'spam' => MailerEngagementEvent::SPAM,
                'unsub' => MailerEngagementEvent::UNSUBSCRIBE,
                default => throw new ParseException(\sprintf('Unsupported event "%s".', $payload['event'])),
            };
            $event = new MailerEngagementEvent($name, $payload['msg']['_id'], $payload);
        }

        if (!$date = \DateTimeImmutable::createFromFormat('U', $payload['msg']['ts'])) {
            throw new ParseException(\sprintf('Invalid date "%s".', $payload['msg']['ts']));
        }
        $event->setDate($date);
        $event->setRecipientEmail($payload['msg']['email']);
        $event->setMetadata($payload['msg']['metadata']);
        $event->setTags($payload['msg']['tags']);

        return $event;
    }

    private function getReason(array $payload): string
    {
        if (null !== $payload['msg']['diag']) {
            return $payload['msg']['diag'];
        }
        if (null !== $payload['msg']['bounce_description']) {
            return $payload['msg']['bounce_description'];
        }

        if (null !== $payload['msg']['smtp_events'] && [] !== $payload['msg']['smtp_events']) {
            $reasons = [];
            foreach ($payload['msg']['smtp_events'] as $event) {
                $reasons[] = \sprintf('type: %s diag: %s', $event['type'], $event['diag']);
            }

            // Return concatenated reasons or an empty string if no reasons found
            return implode(' ', $reasons);
        }

        return '';
    }
}
