<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\Twilio\Webhook;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestMatcher\MethodRequestMatcher;
use Symfony\Component\HttpFoundation\RequestMatcherInterface;
use Symfony\Component\RemoteEvent\Event\Sms\SmsEvent;
use Symfony\Component\Webhook\Client\AbstractRequestParser;
use Symfony\Component\Webhook\Exception\RejectWebhookException;

final class TwilioRequestParser extends AbstractRequestParser
{
    protected function getRequestMatcher(): RequestMatcherInterface
    {
        return new MethodRequestMatcher('POST');
    }

    protected function doParse(Request $request, string $secret): ?SmsEvent
    {
        // Statuses: https://www.twilio.com/docs/sms/api/message-resource#message-status-values
        // Payload examples: https://www.twilio.com/docs/sms/outbound-message-logging
        $payload = $request->request->all();
        if (
            !isset($payload['MessageStatus'])
            || !isset($payload['MessageSid'])
            || !isset($payload['To'])
        ) {
            throw new RejectWebhookException(406, 'Payload is malformed.');
        }

        $name = match ($payload['MessageStatus']) {
            'delivered' => SmsEvent::DELIVERED,
            'failed' => SmsEvent::FAILED,
            'undelivered' => SmsEvent::FAILED,
            'accepted' => null,
            'queued' => null,
            'sending' => null,
            'sent' => null,
            'canceled' => null,
            'receiving' => null,
            'received' => null,
            'scheduled' => null,
            default => throw new RejectWebhookException(406, sprintf('Unsupported event "%s".', $payload['event'])),
        };
        if (!$name) {
            return null;
        }
        $event = new SmsEvent($name, $payload['MessageSid'], $payload);
        $event->setRecipientPhone($payload['To']);

        return $event;
    }
}
