<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Webhook\Test;

use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Webhook\Exception\InvalidArgumentException;
use Symfony\Component\Webhook\Server\SignatureFormat;
use Symfony\Component\Webhook\StandardWebhooksSignature;

/**
 * Creates the webhook requests that the generic request parser of the component accepts, as a sender would post them.
 */
final class RequestFactory
{
    /**
     * @param array<string, string> $secrets The secret of each routing entry parsed by the generic request parser, by type
     */
    public function __construct(
        #[\SensitiveParameter] private readonly array $secrets,
        private readonly string $algo = 'sha256',
        private readonly string $signatureHeaderName = 'Webhook-Signature',
        private readonly string $eventHeaderName = 'Webhook-Event',
        private readonly string $idHeaderName = 'Webhook-Id',
        private readonly string $timestampHeaderName = 'Webhook-Timestamp',
        private readonly SignatureFormat $format = SignatureFormat::Legacy,
        private readonly ?ClockInterface $clock = null,
    ) {
    }

    /**
     * Creates a signed request that carries an event, with no other headers than the ones a webhook sender sets.
     *
     * @param string               $type    The routing entry that the request is meant for
     * @param array<string, mixed> $payload The payload, sent as JSON
     * @param string|null          $secret  A secret to sign the request with, instead of the one of the routing entry
     */
    public function createRequest(string $uri, string $type, string $event, array $payload = [], #[\SensitiveParameter] ?string $secret = null): Request
    {
        if (!isset($this->secrets[$type])) {
            throw new InvalidArgumentException(\sprintf('Cannot sign webhooks of type "%s": only the routing entries that use the generic request parser of the Webhook component can be signed (%s). Create the requests of the other entries by hand.', $type, $this->secrets ? '"'.implode('", "', array_keys($this->secrets)).'"' : 'none'));
        }

        $secret ??= $this->secrets[$type];

        if ('' === $secret) {
            throw new InvalidArgumentException(\sprintf('A non-empty secret is required to sign webhooks of type "%s".', $type));
        }

        // the Standard Webhooks signature covers no header, so the event name is sent in the payload, as JsonBodyConfigurator does
        if (SignatureFormat::Legacy !== $this->format) {
            $payload['type'] ??= $event;
        }

        $id = bin2hex(random_bytes(16));
        $timestamp = (string) ($this->clock?->now()->getTimestamp() ?? time());
        $body = json_encode($payload, \JSON_THROW_ON_ERROR);

        $headers = [
            'Content-Type' => 'application/json',
            $this->idHeaderName => $id,
            $this->timestampHeaderName => $timestamp,
        ];
        $signatures = [];

        if (SignatureFormat::Standard !== $this->format) {
            $headers[$this->eventHeaderName] = $event;
            $signatures[] = $this->algo.'='.hash_hmac($this->algo, $event.$id.$body, $secret);
        }

        if (SignatureFormat::Legacy !== $this->format) {
            $signatures[] = StandardWebhooksSignature::sign($id, $timestamp, $body, $secret);
        }

        $headers[$this->signatureHeaderName] = implode(' ', $signatures);

        $request = Request::create($uri, 'POST', [], [], [], [], $body);
        $request->headers->replace($headers);

        return $request;
    }
}
