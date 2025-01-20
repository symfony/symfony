<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mailer\Bridge\AhaSend\Webhook;

use Symfony\Component\HttpFoundation\ChainRequestMatcher;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestMatcher\IsJsonRequestMatcher;
use Symfony\Component\HttpFoundation\RequestMatcher\MethodRequestMatcher;
use Symfony\Component\HttpFoundation\RequestMatcherInterface;
use Symfony\Component\Mailer\Bridge\AhaSend\RemoteEvent\AhaSendPayloadConverter;
use Symfony\Component\RemoteEvent\Event\Mailer\AbstractMailerEvent;
use Symfony\Component\RemoteEvent\Exception\ParseException;
use Symfony\Component\Webhook\Client\AbstractRequestParser;
use Symfony\Component\Webhook\Exception\RejectWebhookException;

final class AhaSendRequestParser extends AbstractRequestParser
{
    public function __construct(
        private readonly AhaSendPayloadConverter $converter,
    ) {
    }

    protected function getRequestMatcher(): RequestMatcherInterface
    {
        return new ChainRequestMatcher([
            new MethodRequestMatcher('POST'),
            new IsJsonRequestMatcher(),
        ]);
    }

    protected function doParse(Request $request, #[\SensitiveParameter] string $secret): ?AbstractMailerEvent
    {
        $payload = $request->toArray();
        $eventID = $request->headers->get('webhook-id');
        $signature = $request->headers->get('webhook-signature');
        $timestamp = $request->headers->get('webhook-timestamp');
        if (empty($eventID) || empty($signature) || empty($timestamp)) {
            throw new RejectWebhookException(406, 'Signature is required.');
        }
        if (!is_numeric($timestamp) || \is_float($timestamp + 0) || (int) $timestamp != $timestamp || (int) $timestamp <= 0) {
            throw new RejectWebhookException(406, 'Invalid timestamp.');
        }
        $expectedSignature = $this->sign($eventID, $timestamp, $request->getContent(), $secret);
        if ($signature !== $expectedSignature) {
            throw new RejectWebhookException(406, 'Invalid signature');
        }
        if (!isset($payload['type']) || !isset($payload['timestamp']) || !(isset($payload['data']))) {
            throw new RejectWebhookException(406, 'Payload is malformed.');
        }

        try {
            return $this->converter->convert($payload);
        } catch (ParseException $e) {
            throw new RejectWebhookException(406, $e->getMessage(), $e);
        }
    }

    private function sign(string $eventID, string $timestamp, string $payload, $secret): string
    {
        $signaturePayload = "{$eventID}.{$timestamp}.{$payload}";
        $hash = hash_hmac('sha256', $signaturePayload, $secret);
        $signature = base64_encode(pack('H*', $hash));

        return "v1,{$signature}";
    }
}
