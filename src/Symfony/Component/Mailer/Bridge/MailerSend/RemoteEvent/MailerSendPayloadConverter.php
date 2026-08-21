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
    private ?int $whVer;

    public function convert(array $payload): AbstractMailerEvent
    {
        $this->whVer = isset($payload['data']['email']['message']['id']) ? 1 : 2;

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
        if (1 === $this->whVer) {
            return $payload['data']['email']['message']['id'];
        }

        return $payload['data']['message_id'];
    }

    private function getRecipientEmail(array $payload): string
    {
        if (1 === $this->whVer) {
            return $payload['data']['email']['recipient']['email'];
        }

        return $payload['data']['recipient'];
    }

    private function getReason(array $payload): string
    {
        if (1 === $this->whVer) {
            if (isset($payload['data']['morph']['readable_reason'])) {
                return $payload['data']['morph']['readable_reason'];
            }

            if (isset($payload['data']['morph']['reason'])) {
                return $payload['data']['morph']['reason'];
            }

            return '';
        } else {
            return $payload['data']['meta']['bounce_reason'] ?? '';
        }
    }

    private function getTags(array $payload): array
    {
        if (1 === $this->whVer) {
            return $payload['data']['email']['tags'] ?? [];
        }

        return $payload['data']['tags'] ?? [];
    }

    private function getMetadata(array $payload): array
    {
        if (1 === $this->whVer) {
            $morphObject = $payload['data']['morph']['object'] ?? null;

            return match ($morphObject) {
                'open' => [
                    'ip' => $payload['data']['morph']['ip'] ?? null,
                ],
                'click' => [
                    'ip' => $payload['data']['morph']['ip'] ?? null,
                    'url' => $payload['data']['morph']['url'] ?? null,
                ],
                'recipient_unsubscribe' => [
                    'reason' => $payload['data']['morph']['reason'] ?? null,
                    'readable_reason' => $payload['data']['morph']['readable_reason'] ?? null,
                ],
                default => [],
            };
        }

        return match ($payload['type']) {
            'activity.opened',  => [
                'ip' => $payload['data']['meta']['ip'] ?? null,
            ],
            'activity.clicked',  => [
                'ip' => $payload['data']['meta']['ip'] ?? null,
                'url' => $payload['data']['meta']['url'] ?? null,
            ],
            'activity.unsubscribed',  => [
                'reason' => $payload['data']['meta']['unsubscribe_reason'] ?? null,
            ],
            default => [],
        };
    }
}
