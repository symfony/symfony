<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mailer\Bridge\Sendgrid\Webhook;

use Symfony\Component\HttpFoundation\ChainRequestMatcher;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestMatcher\IsJsonRequestMatcher;
use Symfony\Component\HttpFoundation\RequestMatcher\MethodRequestMatcher;
use Symfony\Component\HttpFoundation\RequestMatcherInterface;
use Symfony\Component\Mailer\Bridge\Sendgrid\RemoteEvent\SendgridPayloadConverter;
use Symfony\Component\Mailer\Exception\InvalidArgumentException;
use Symfony\Component\RemoteEvent\Event\Mailer\AbstractMailerEvent;
use Symfony\Component\RemoteEvent\Exception\ParseException;
use Symfony\Component\Webhook\Client\AbstractRequestParser;
use Symfony\Component\Webhook\Exception\RejectWebhookException;

/**
 * @author WoutervanderLoop.nl <info@woutervanderloop.nl>
 */
final class SendgridRequestParser extends AbstractRequestParser
{
    public function __construct(
        private readonly SendgridPayloadConverter $converter,
    ) {
    }

    protected function getRequestMatcher(): RequestMatcherInterface
    {
        return new ChainRequestMatcher([
            new MethodRequestMatcher('POST'),
            new IsJsonRequestMatcher(),
        ]);
    }

    protected function doParse(Request $request, string $secret): ?AbstractMailerEvent
    {
        $content = $request->toArray();
        if (
            !isset($content[0]['email'])
            || !isset($content[0]['timestamp'])
            || !isset($content[0]['event'])
            || !isset($content[0]['sg_message_id'])
        ) {
            throw new RejectWebhookException(406, 'Payload is malformed.');
        }

        if ($secret) {
            if (!$request->headers->get('X-Twilio-Email-Event-Webhook-Signature')
                || !$request->headers->get('X-Twilio-Email-Event-Webhook-Timestamp')
            ) {
                throw new RejectWebhookException(406, 'Signature is required.');
            }

            $this->validateSignature(
                $request->headers->get('X-Twilio-Email-Event-Webhook-Signature'),
                $request->headers->get('X-Twilio-Email-Event-Webhook-Timestamp'),
                $request->getContent(),
                $secret,
            );
        }

        try {
            return $this->converter->convert($content[0]);
        } catch (ParseException $e) {
            throw new RejectWebhookException(406, $e->getMessage(), $e);
        }
    }

    /**
     * Verify signed event webhook requests.
     *
     * @param string $signature value obtained from the
     *                          'X-Twilio-Email-Event-Webhook-Signature' header
     * @param string $timestamp value obtained from the
     *                          'X-Twilio-Email-Event-Webhook-Timestamp' header
     * @param string $payload   event payload in the request body
     * @param string $secret    base64-encoded DER public key
     *
     * @see https://docs.sendgrid.com/for-developers/tracking-events/getting-started-event-webhook-security-features
     */
    private function validateSignature(string $signature, string $timestamp, string $payload, #[\SensitiveParameter] string $secret): void
    {
        if (!$secret) {
            throw new InvalidArgumentException('A non-empty secret is required.');
        }

        $timestampedPayload = $timestamp.$payload;

        // Sendgrid provides the verification key as base64-encoded DER data. Openssl wants a PEM format, which is a multiline version of the base64 data.
        $pemKey = "-----BEGIN PUBLIC KEY-----\n".chunk_split($secret, 64, "\n")."-----END PUBLIC KEY-----\n";

        if (!$publicKey = openssl_pkey_get_public($pemKey)) {
            throw new RejectWebhookException(406, 'Public key is wrong.');
        }

        if (1 !== openssl_verify($timestampedPayload, base64_decode($signature), $publicKey, \OPENSSL_ALGO_SHA256)) {
            throw new RejectWebhookException(406, 'Signature is wrong.');
        }
    }
}
