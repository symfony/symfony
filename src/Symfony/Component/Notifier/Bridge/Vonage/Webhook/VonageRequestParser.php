<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\Vonage\Webhook;

use Symfony\Component\HttpFoundation\ChainRequestMatcher;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestMatcher\IsJsonRequestMatcher;
use Symfony\Component\HttpFoundation\RequestMatcher\MethodRequestMatcher;
use Symfony\Component\HttpFoundation\RequestMatcherInterface;
use Symfony\Component\Notifier\Exception\InvalidArgumentException;
use Symfony\Component\RemoteEvent\Event\Sms\SmsEvent;
use Symfony\Component\Webhook\Client\AbstractRequestParser;
use Symfony\Component\Webhook\Exception\RejectWebhookException;

final class VonageRequestParser extends AbstractRequestParser
{
    protected function getRequestMatcher(): RequestMatcherInterface
    {
        return new ChainRequestMatcher([
            new MethodRequestMatcher('POST'),
            new IsJsonRequestMatcher(),
        ]);
    }

    protected function doParse(Request $request, #[\SensitiveParameter] string $secret): ?SmsEvent
    {
        if (!$secret) {
            throw new InvalidArgumentException('A non-empty secret is required.');
        }

        // Signed webhooks: https://developer.vonage.com/en/getting-started/concepts/webhooks#validating-signed-webhooks
        if (!$request->headers->has('Authorization')) {
            throw new RejectWebhookException(406, 'Missing "Authorization" header.');
        }
        $this->validateSignature(substr($request->headers->get('Authorization'), \strlen('Bearer ')), $secret);

        // Statuses: https://developer.vonage.com/en/api/messages-olympus#message-status
        $payload = $request->toArray();
        if (
            !isset($payload['status'])
            || !isset($payload['message_uuid'])
            || !isset($payload['to'])
            || !isset($payload['channel'])
        ) {
            throw new RejectWebhookException(406, 'Payload is malformed.');
        }

        if ('sms' !== $payload['channel']) {
            throw new RejectWebhookException(406, \sprintf('Unsupported channel "%s".', $payload['channel']));
        }

        $name = match ($payload['status']) {
            'delivered' => SmsEvent::DELIVERED,
            'rejected' => SmsEvent::FAILED,
            'submitted' => null,
            'undeliverable' => SmsEvent::FAILED,
            default => throw new RejectWebhookException(406, \sprintf('Unsupported event "%s".', $payload['status'])),
        };
        if (!$name) {
            return null;
        }

        $event = new SmsEvent($name, $payload['message_uuid'], $payload);
        $event->setRecipientPhone($payload['to']);

        return $event;
    }

    private function validateSignature(string $jwt, #[\SensitiveParameter] string $secret): void
    {
        $tokenParts = explode('.', $jwt);
        if (3 !== \count($tokenParts)) {
            throw new RejectWebhookException(406, 'Signature is wrong.');
        }

        [$header, $payload, $signature] = $tokenParts;
        if ($signature !== $this->base64EncodeUrl(hash_hmac('sha256', $header.'.'.$payload, $secret, true))) {
            throw new RejectWebhookException(406, 'Signature is wrong.');
        }
    }

    private function base64EncodeUrl(string $string): string
    {
        return str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($string));
    }
}
