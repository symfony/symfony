<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mailer\Bridge\MailerSend\RemoteEvent;

use Symfony\Component\RemoteEvent\Event\Mailer\AbstractMailerEvent;
use Symfony\Component\RemoteEvent\Event\Mailer\MailerDeliveryEvent;
use Symfony\Component\RemoteEvent\Event\Mailer\MailerEngagementEvent;
use Symfony\Component\RemoteEvent\Exception\ParseException;
use Symfony\Component\RemoteEvent\PayloadConverterInterface;

/**
 * @author WoutervanderLoop.nl <info@woutervanderloop.nl>
 */
final class MailerSendPayloadConverter implements PayloadConverterInterface
{
    public function convert(array $payload): AbstractMailerEvent
    {
        if (\in_array($payload['type'], ['activity.sent', 'activity.delivered', 'activity.soft_bounced', 'activity.hard_bounced'], true)) {
            $name = match ($payload['type']) {
                'activity.sent' => MailerDeliveryEvent::RECEIVED,
                'activity.delivered' => MailerDeliveryEvent::DELIVERED,
                'activity.soft_bounced', 'activity.hard_bounced' => MailerDeliveryEvent::BOUNCE,
            };
            $event = new MailerDeliveryEvent($name, $this->getMessageId($payload), $payload);
            $event->setReason($this->getReason($payload));
        } else {
            $name = match ($payload['type']) {
                'activity.clicked', 'activity.clicked_unique' => MailerEngagementEvent::CLICK,
                'activity.unsubscribed' => MailerEngagementEvent::UNSUBSCRIBE,
                'activity.opened', 'activity.opened_unique' => MailerEngagementEvent::OPEN,
                'activity.spam_complaint' => MailerEngagementEvent::SPAM,
                default => throw new ParseException(\sprintf('Unsupported event "%s".', $payload['type'])),
            };
            $event = new MailerEngagementEvent($name, $this->getMessageId($payload), $payload);
        }

        if (!$date = \DateTimeImmutable::createFromFormat('Y-m-d\TH:i:s.uP', $payload['created_at'])) {
            throw new ParseException(\sprintf('Invalid date "%s".', $payload['created_at']));
        }

        $event->setDate($date);
        $event->setRecipientEmail($this->getRecipientEmail($payload));
        $event->setMetadata($this->getMetadata($payload));
        $event->setTags($this->getTags($payload));

        return $event;
    }

    private function getMessageId(array $payload): string
    {
        return $payload['data']['message_id'] ?? $payload['data']['email']['message']['id'];
    }

    private function getRecipientEmail(array $payload): string
    {
        return $payload['data']['email']['recipient']['email'] ?? $payload['data']['recipient'] ?? $payload['data']['email'];
    }

    private function getReason(array $payload): string
    {
        return $payload['data']['morph']['readable_reason']
            ?? $payload['data']['morph']['reason']
            ?? $payload['data']['meta']['bounce_reason']
            ?? '';
    }

    private function getTags(array $payload): array
    {
        return $payload['data']['email']['tags'] ?? $payload['data']['tags'] ?? [];
    }

    private function getMetadata(array $payload): array
    {
        if (!$meta = $payload['data']['morph'] ?? $payload['data']['meta'] ?? []) {
            return [];
        }

        return match ($payload['type']) {
            'activity.opened', 'activity.opened_unique' => [
                'ip' => $meta['ip'] ?? null,
            ],
            'activity.clicked', 'activity.clicked_unique' => [
                'ip' => $meta['ip'] ?? null,
                'url' => $meta['url'] ?? null,
            ],
            'activity.unsubscribed' => [
                'reason' => $meta['reason'] ?? $meta['unsubscribe_reason'] ?? null,
                'readable_reason' => $meta['readable_reason'] ?? null,
            ],
            default => [],
        };
    }
}
