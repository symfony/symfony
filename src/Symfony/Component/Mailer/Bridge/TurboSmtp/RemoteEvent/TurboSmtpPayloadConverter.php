<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mailer\Bridge\TurboSmtp\RemoteEvent;

use Symfony\Component\RemoteEvent\Event\Mailer\AbstractMailerEvent;
use Symfony\Component\RemoteEvent\Event\Mailer\MailerDeliveryEvent;
use Symfony\Component\RemoteEvent\Event\Mailer\MailerEngagementEvent;
use Symfony\Component\RemoteEvent\Exception\ParseException;
use Symfony\Component\RemoteEvent\PayloadConverterInterface;

/**
 * @author Dominik Spitzli <dominik@spitzli.dev>
 */
final class TurboSmtpPayloadConverter implements PayloadConverterInterface
{
    public function convert(array $payload): AbstractMailerEvent
    {
        $status = strtolower($payload['status']);

        if (\in_array($status, ['processed', 'delivered', 'deferred', 'bounce', 'bounced', 'dropped'], true)) {
            $name = match ($status) {
                'processed' => MailerDeliveryEvent::RECEIVED,
                'delivered' => MailerDeliveryEvent::DELIVERED,
                'deferred' => MailerDeliveryEvent::DEFERRED,
                'bounce', 'bounced' => MailerDeliveryEvent::BOUNCE,
                'dropped' => MailerDeliveryEvent::DROPPED,
            };
            $event = new MailerDeliveryEvent($name, (string) $payload['mid'], $payload);

            // deferrals carry a plain string, drops and bounces an object holding the response of the receiving server
            $reason = $payload['reason'] ?? '';
            $event->setReason(\is_array($reason) ? ($reason['response'] ?? '') : $reason);
        } else {
            $name = match ($status) {
                'open', 'opened' => MailerEngagementEvent::OPEN,
                'click', 'clicked' => MailerEngagementEvent::CLICK,
                'unsubscribe', 'unsubscribed' => MailerEngagementEvent::UNSUBSCRIBE,
                'report', 'spam', 'spam report', 'spamreport' => MailerEngagementEvent::SPAM,
                default => throw new ParseException(\sprintf('Unsupported event "%s".', $payload['status'])),
            };
            $event = new MailerEngagementEvent($name, (string) $payload['mid'], $payload);
        }

        $timestamp = $payload['Timestamp'] ?? $payload['timestamp'] ?? '';

        if (!$date = \DateTimeImmutable::createFromFormat('U', $timestamp)) {
            throw new ParseException(\sprintf('Invalid date "%s".', $timestamp));
        }

        $event->setDate($date);
        $event->setRecipientEmail($payload['email']);

        return $event;
    }
}
