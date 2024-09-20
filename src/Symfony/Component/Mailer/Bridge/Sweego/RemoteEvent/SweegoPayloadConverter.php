<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mailer\Bridge\Sweego\RemoteEvent;

use Symfony\Component\RemoteEvent\Event\Mailer\AbstractMailerEvent;
use Symfony\Component\RemoteEvent\Event\Mailer\MailerDeliveryEvent;
use Symfony\Component\RemoteEvent\Exception\ParseException;
use Symfony\Component\RemoteEvent\PayloadConverterInterface;

final class SweegoPayloadConverter implements PayloadConverterInterface
{
    public function convert(array $payload): AbstractMailerEvent
    {
        if (\in_array($payload['event_type'], ['email_sent', 'delivered'], true)) {
            $name = match ($payload['event_type']) {
                'email_sent' => MailerDeliveryEvent::RECEIVED,
                'delivered' => MailerDeliveryEvent::DELIVERED,
            };

            $event = new MailerDeliveryEvent($name, $payload['headers']['x-transaction-id'], $payload);
        }

        if (!$date = \DateTimeImmutable::createFromFormat(\DATE_ATOM, $payload['timestamp'])) {
            throw new ParseException(\sprintf('Invalid date "%s".', $payload['timestamp']));
        }

        $event->setDate($date);
        $event->setRecipientEmail($payload['recipient']);
        $event->setMetadata($payload['headers']);

        return $event;
    }
}
