<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mailer\Bridge\Sendora\Webhook;

use Psr\Clock\ClockInterface;
use Symfony\Component\HttpFoundation\ChainRequestMatcher;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestMatcher\HeaderRequestMatcher;
use Symfony\Component\HttpFoundation\RequestMatcher\IsJsonRequestMatcher;
use Symfony\Component\HttpFoundation\RequestMatcher\MethodRequestMatcher;
use Symfony\Component\HttpFoundation\RequestMatcherInterface;
use Symfony\Component\Mailer\Bridge\Sendora\RemoteEvent\SendoraPayloadConverter;
use Symfony\Component\Mailer\Exception\InvalidArgumentException;
use Symfony\Component\RemoteEvent\Exception\ParseException;
use Symfony\Component\RemoteEvent\RemoteEvent;
use Symfony\Component\Webhook\Client\AbstractRequestParser;
use Symfony\Component\Webhook\Exception\RejectWebhookException;

/**
 * @see https://sendora.se/docs/webhooks#verify-the-signature
 */
final class SendoraRequestParser extends AbstractRequestParser
{
    private const TIMESTAMP_TOLERANCE = 300;

    public function __construct(
        private readonly SendoraPayloadConverter $converter,
        private readonly ?ClockInterface $clock = null,
    ) {
    }

    protected function getRequestMatcher(): RequestMatcherInterface
    {
        return new ChainRequestMatcher([
            new MethodRequestMatcher('POST'),
            new IsJsonRequestMatcher(),
            new HeaderRequestMatcher('Sendora-Signature'),
        ]);
    }

    protected function doParse(Request $request, #[\SensitiveParameter] string $secret): RemoteEvent
    {
        if (!$secret) {
            throw new InvalidArgumentException('A non-empty secret is required.');
        }

        $this->validateSignature($request, $secret);

        $payload = $request->toArray();

        if (!\is_string($payload['event'] ?? null) || !\is_string($payload['id'] ?? null)) {
            throw new RejectWebhookException(406, 'Payload is malformed.');
        }

        try {
            return $this->converter->convert($payload);
        } catch (ParseException $e) {
            throw new RejectWebhookException(406, $e->getMessage(), $e);
        }
    }

    private function validateSignature(Request $request, #[\SensitiveParameter] string $secret): void
    {
        // "t=<unix seconds>,v1=<hex>", with a v1 signature for each live secret of the webhook
        $timestamp = null;
        $signatures = [];
        foreach (explode(',', $request->headers->get('Sendora-Signature')) as $part) {
            [$name, $value] = explode('=', trim($part), 2) + [1 => ''];

            if ('t' === $name) {
                $timestamp = $value;
            } elseif ('v1' === $name) {
                $signatures[] = $value;
            }
        }

        if (!ctype_digit((string) $timestamp)) {
            throw new RejectWebhookException(406, 'Invalid signature.');
        }

        if (abs(($this->clock?->now()->getTimestamp() ?? time()) - (int) $timestamp) > self::TIMESTAMP_TOLERANCE) {
            throw new RejectWebhookException(406, 'Timestamp is outside the allowed time window.');
        }

        $expectedSignature = hash_hmac('sha256', $timestamp.'.'.$request->getContent(), $secret);

        foreach ($signatures as $signature) {
            if (hash_equals($expectedSignature, $signature)) {
                return;
            }
        }

        throw new RejectWebhookException(406, 'Invalid signature.');
    }
}
