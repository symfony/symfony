<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mailer\Bridge\Azure\RemoteEvent;

use Symfony\Component\RemoteEvent\Event\Mailer\AbstractMailerEvent;
use Symfony\Component\RemoteEvent\Event\Mailer\MailerDeliveryEvent;
use Symfony\Component\RemoteEvent\Event\Mailer\MailerEngagementEvent;
use Symfony\Component\RemoteEvent\Exception\ParseException;
use Symfony\Component\RemoteEvent\PayloadConverterInterface;

final class AzurePayloadConverter implements PayloadConverterInterface
{
    public function convert(array $payload): AbstractMailerEvent
    {
        $eventType = $payload['eventType'] ?? null;

        if ('Microsoft.Communication.EmailDeliveryReportReceived' === $eventType) {
            return $this->convertDeliveryEvent($payload['data']);
        }

        if ('Microsoft.Communication.EmailEngagementTrackingReportReceived' === $eventType) {
            return $this->convertEngagementEvent($payload['data']);
        }

        throw new ParseException(\sprintf('Unsupported event type "%s".', $eventType));
    }

    private function convertDeliveryEvent(array $data): MailerDeliveryEvent
    {
        if (!\is_string($data['status'] ?? null) || !\is_string($data['recipient'] ?? null) || !\is_string($data['deliveryAttemptTimeStamp'] ?? null)) {
            throw new ParseException('Payload is missing one of the "status", "recipient" or "deliveryAttemptTimeStamp" keys, or holds a non-string value for one of them.');
        }

        $name = match ($data['status']) {
            'Delivered' => MailerDeliveryEvent::DELIVERED,
            'Bounced' => MailerDeliveryEvent::BOUNCE,
            'Suppressed', 'Quarantined', 'FilteredSpam', 'Failed' => MailerDeliveryEvent::DROPPED,
            'Expanded' => MailerDeliveryEvent::DELIVERED,
            default => throw new ParseException(\sprintf('Unsupported delivery status "%s".', $data['status'])),
        };

        $event = new MailerDeliveryEvent($name, $data['messageId'], $data);

        if (\is_string($data['deliveryStatusDetails']['statusMessage'] ?? null)) {
            $event->setReason($data['deliveryStatusDetails']['statusMessage']);
        }

        $event->setRecipientEmail($data['recipient']);

        $date = $this->parseDate($data['deliveryAttemptTimeStamp']);
        $event->setDate($date);

        return $event;
    }

    private function convertEngagementEvent(array $data): MailerEngagementEvent
    {
        if (!\is_string($data['engagementType'] ?? null) || !\is_string($data['recipient'] ?? null) || !\is_string($data['userActionTimeStamp'] ?? null)) {
            throw new ParseException('Payload is missing one of the "engagementType", "recipient" or "userActionTimeStamp" keys, or holds a non-string value for one of them.');
        }

        $name = match (strtolower($data['engagementType'])) {
            'view' => MailerEngagementEvent::OPEN,
            'click' => MailerEngagementEvent::CLICK,
            default => throw new ParseException(\sprintf('Unsupported engagement type "%s".', $data['engagementType'])),
        };

        $event = new MailerEngagementEvent($name, $data['messageId'], $data);

        $event->setRecipientEmail($data['recipient']);

        $date = $this->parseDate($data['userActionTimeStamp']);
        $event->setDate($date);

        return $event;
    }

    private function parseDate(string $dateString): \DateTimeImmutable
    {
        // Azure uses ISO 8601 format with timezone offset
        // Microseconds can be 6 or 7 digits, normalize to 6 digits
        $normalizedDate = preg_replace('/(\.\d{6})\d+/', '$1', $dateString);

        $date = \DateTimeImmutable::createFromFormat(\DateTimeInterface::ATOM, $normalizedDate)
            ?: \DateTimeImmutable::createFromFormat('Y-m-d\TH:i:s.uP', $normalizedDate)
            ?: \DateTimeImmutable::createFromFormat('Y-m-d\TH:i:sP', $normalizedDate);

        if (!$date) {
            throw new ParseException(\sprintf('Invalid date "%s".', $dateString));
        }

        return $date;
    }
}
