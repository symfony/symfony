<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Webhook\Client;

use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\HttpFoundation\ChainRequestMatcher;
use Symfony\Component\HttpFoundation\HeaderBag;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestMatcher\IsJsonRequestMatcher;
use Symfony\Component\HttpFoundation\RequestMatcher\MethodRequestMatcher;
use Symfony\Component\HttpFoundation\RequestMatcherInterface;
use Symfony\Component\RemoteEvent\RemoteEvent;
use Symfony\Component\Webhook\Exception\InvalidArgumentException;
use Symfony\Component\Webhook\Exception\RejectWebhookException;
use Symfony\Component\Webhook\Server\SignatureFormat;
use Symfony\Component\Webhook\StandardWebhooksSignature;

/**
 * @author Fabien Potencier <fabien@symfony.com>
 */
class RequestParser extends AbstractRequestParser
{
    public function __construct(
        private readonly string $algo = 'sha256',
        private readonly string $signatureHeaderName = 'Webhook-Signature',
        private readonly string $eventHeaderName = 'Webhook-Event',
        private readonly string $idHeaderName = 'Webhook-Id',
        private readonly string $timestampHeaderName = 'Webhook-Timestamp',
        private readonly SignatureFormat $format = SignatureFormat::Legacy,
        private readonly int $timestampTolerance = 300,
        private readonly ?ClockInterface $clock = null,
    ) {
    }

    protected function getRequestMatcher(): RequestMatcherInterface
    {
        return new ChainRequestMatcher([
            new MethodRequestMatcher('POST'),
            new IsJsonRequestMatcher(),
        ]);
    }

    protected function doParse(Request $request, #[\SensitiveParameter] string $secret): RemoteEvent
    {
        if (!$secret) {
            throw new InvalidArgumentException('A non-empty secret is required.');
        }

        $payload = $request->toArray();

        $required = [$this->signatureHeaderName, $this->idHeaderName];
        if (SignatureFormat::Legacy === $this->format) {
            $required[] = $this->eventHeaderName;
        } elseif (SignatureFormat::Standard === $this->format) {
            $required[] = $this->timestampHeaderName;
        }

        foreach ($required as $header) {
            if (!$request->headers->has($header)) {
                throw new RejectWebhookException(406, \sprintf('Missing "%s" HTTP request signature header.', $header));
            }
        }

        $verified = $this->verifySignature($request->headers, $request->getContent(), $secret);

        return new RemoteEvent(
            SignatureFormat::Standard === $verified ? $this->eventName($payload) : $request->headers->get($this->eventHeaderName),
            $request->headers->get($this->idHeaderName),
            $payload,
        );
    }

    /**
     * @return SignatureFormat The scheme of the entry that authenticated the request
     */
    private function verifySignature(HeaderBag $headers, string $body, #[\SensitiveParameter] string $secret): SignatureFormat
    {
        // the header may carry several whitespace-separated entries, one per secret during a
        // rotation; each expectation is computed once so that the number of entries a request
        // can carry does not drive the CPU cost of rejecting it
        $candidates = preg_split('/\s++/', trim($headers->get($this->signatureHeaderName)), -1, \PREG_SPLIT_NO_EMPTY);
        $id = $headers->get($this->idHeaderName);

        // the Standard Webhooks entry comes first, so that a request carrying both is replay-bounded
        if (SignatureFormat::Legacy !== $this->format && null !== $timestamp = $headers->get($this->timestampHeaderName)) {
            $expected = StandardWebhooksSignature::sign($id, $timestamp, $body, $secret);

            foreach ($candidates as $candidate) {
                if (hash_equals($expected, $candidate)) {
                    $this->checkTimestamp($timestamp);

                    return SignatureFormat::Standard;
                }
            }
        }

        if (SignatureFormat::Standard !== $this->format && null !== $event = $headers->get($this->eventHeaderName)) {
            $expected = $this->algo.'='.hash_hmac($this->algo, $event.$id.$body, $secret);

            foreach ($candidates as $candidate) {
                if (hash_equals($expected, $candidate)) {
                    return SignatureFormat::Legacy;
                }
            }
        }

        throw new RejectWebhookException(406, 'Signature is wrong.');
    }

    private function checkTimestamp(string $timestamp): void
    {
        if (0 >= $this->timestampTolerance) {
            return;
        }

        $now = $this->clock?->now()->getTimestamp() ?? time();

        // a value PHP cannot read as an integer is not a timestamp we can bound
        if (!preg_match('/^-?+\d++$/', $timestamp) || abs($now - (int) $timestamp) > $this->timestampTolerance) {
            throw new RejectWebhookException(406, \sprintf('The "%s" HTTP request header is outside the accepted window.', $this->timestampHeaderName));
        }
    }

    private function eventName(array $payload): string
    {
        if (!\is_string($payload['type'] ?? null)) {
            throw new RejectWebhookException(406, 'The payload is missing the "type" key holding the event name.');
        }

        return $payload['type'];
    }
}
