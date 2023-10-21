<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mailer\Bridge\Postmark\RemoteEvent;

use Symfony\Component\RemoteEvent\Event\Mailer\AbstractMailerEvent;
use Symfony\Component\RemoteEvent\Event\Mailer\MailerDeliveryEvent;
use Symfony\Component\RemoteEvent\Event\Mailer\MailerEngagementEvent;
use Symfony\Component\RemoteEvent\Exception\ParseException;
use Symfony\Component\RemoteEvent\PayloadConverterInterface;

final class PostmarkPayloadConverter implements PayloadConverterInterface
{
    public function convert(array $payload): AbstractMailerEvent
    {
        if (\in_array($payload['RecordType'], ['Delivery', 'Bounce'], true)) {
            $name = match ($payload['RecordType']) {
                'Delivery' => MailerDeliveryEvent::DELIVERED,
                'Bounce' => MailerDeliveryEvent::BOUNCE,
            };
            $event = new MailerDeliveryEvent($name, $payload['MessageID'], $payload);
            $event->setReason($payload['Description'] ?? '');
        } else {
            $name = match ($payload['RecordType']) {
                'Click' => MailerEngagementEvent::CLICK,
                'SubscriptionChange' => MailerEngagementEvent::UNSUBSCRIBE,
                'Open' => MailerEngagementEvent::OPEN,
                'SpamComplaint' => MailerEngagementEvent::SPAM,
                default => throw new ParseException(sprintf('Unsupported event "%s".', $payload['RecordType'])),
            };
            $event = new MailerEngagementEvent($name, $payload['MessageID'], $payload);
        }
        $payloadDate = match ($payload['RecordType']) {
            'Delivery' => $payload['DeliveredAt'],
            'Bounce' => $payload['BouncedAt'],
            'Click' => $payload['ReceivedAt'],
            'SubscriptionChange' => $payload['ChangedAt'],
            'Open' => $payload['ReceivedAt'],
            'SpamComplaint' => $payload['BouncedAt'],
            default => throw new ParseException(sprintf('Unsupported event "%s".', $payload['RecordType'])),
        };
        if (!$date = \DateTimeImmutable::createFromFormat('Y-m-d\TH:i:sT', $payloadDate)) {
            throw new ParseException(sprintf('Invalid date "%s".', $payloadDate));
        }
        $event->setDate($date);
        $event->setRecipientEmail($payload['Recipient'] ?? $payload['Email']);
        $event->setMetadata($payload['Metadata']);
        $event->setTags([$payload['Tag']]);

        return $event;
    }
}
