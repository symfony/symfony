<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\Sweego\Webhook;

use Symfony\Component\HttpFoundation\ChainRequestMatcher;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestMatcher\HeaderRequestMatcher;
use Symfony\Component\HttpFoundation\RequestMatcher\IsJsonRequestMatcher;
use Symfony\Component\HttpFoundation\RequestMatcher\MethodRequestMatcher;
use Symfony\Component\HttpFoundation\RequestMatcherInterface;
use Symfony\Component\RemoteEvent\Event\Sms\SmsEvent;
use Symfony\Component\Webhook\Client\AbstractRequestParser;
use Symfony\Component\Webhook\Exception\RejectWebhookException;

/**
 * @author Mathieu Santostefano <msantostefano@protonmail.com>
 *
 * @see https://learn.sweego.io/docs/webhooks/sms_events
 */
final class SweegoRequestParser extends AbstractRequestParser
{
    protected function getRequestMatcher(): RequestMatcherInterface
    {
        return new ChainRequestMatcher([
            new MethodRequestMatcher('POST'),
            new IsJsonRequestMatcher(),
            new HeaderRequestMatcher(['webhook-id', 'webhook-timestamp', 'webhook-signature']),
        ]);
    }

    protected function doParse(Request $request, #[\SensitiveParameter] string $secret): ?SmsEvent
    {
        $payload = $request->toArray();

        if (!isset($payload['event_type']) || !isset($payload['swg_uid']) || !isset($payload['phone_number'])) {
            throw new RejectWebhookException(406, 'Payload is malformed.');
        }

        $this->validateSignature($request, $secret);

        $name = match ($payload['event_type']) {
            'sms_sent' => SmsEvent::DELIVERED,
            default => throw new RejectWebhookException(406, \sprintf('Unsupported event "%s".', $payload['event'])),
        };

        $event = new SmsEvent($name, $payload['swg_uid'], $payload);
        $event->setRecipientPhone($payload['phone_number']);

        return $event;
    }

    private function validateSignature(Request $request, string $secret): void
    {
        $contentToSign = \sprintf(
            '%s.%s.%s',
            $request->headers->get('webhook-id'),
            $request->headers->get('webhook-timestamp'),
            $request->getContent(),
        );

        $computedSignature = base64_encode(hash_hmac('sha256', $contentToSign, base64_decode($secret), true));

        if (!hash_equals($computedSignature, $request->headers->get('webhook-signature'))) {
            throw new RejectWebhookException(403, 'Invalid signature.');
        }
    }
}
