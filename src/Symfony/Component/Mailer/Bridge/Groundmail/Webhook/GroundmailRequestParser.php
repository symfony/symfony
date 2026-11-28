<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mailer\Bridge\Groundmail\Webhook;

use Psr\Clock\ClockInterface;
use Symfony\Component\HttpFoundation\ChainRequestMatcher;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestMatcher\HeaderRequestMatcher;
use Symfony\Component\HttpFoundation\RequestMatcher\IsJsonRequestMatcher;
use Symfony\Component\HttpFoundation\RequestMatcher\MethodRequestMatcher;
use Symfony\Component\HttpFoundation\RequestMatcherInterface;
use Symfony\Component\Mailer\Bridge\Groundmail\RemoteEvent\GroundmailPayloadConverter;
use Symfony\Component\Mailer\Exception\InvalidArgumentException;
use Symfony\Component\RemoteEvent\Event\Mailer\AbstractMailerEvent;
use Symfony\Component\RemoteEvent\Exception\ParseException;
use Symfony\Component\Webhook\Client\AbstractRequestParser;
use Symfony\Component\Webhook\Exception\RejectWebhookException;

/**
 * @see https://docs.groundmail.dev/api/webhooks#signature-verification
 *
 * @author Shivansh Mehendiratta <shivansh@backslope.studio>
 */
final class GroundmailRequestParser extends AbstractRequestParser
{
    private const TIMESTAMP_TOLERANCE = 300;

    public function __construct(
        private readonly GroundmailPayloadConverter $converter,
        private readonly ?ClockInterface $clock = null,
    ) {
    }

    protected function getRequestMatcher(): RequestMatcherInterface
    {
        return new ChainRequestMatcher([
            new MethodRequestMatcher('POST'),
            new IsJsonRequestMatcher(),
            new HeaderRequestMatcher('Groundmail-Signature'),
        ]);
    }

    protected function doParse(Request $request, #[\SensitiveParameter] string $secret): AbstractMailerEvent
    {
        if (!$secret) {
            throw new InvalidArgumentException('A non-empty secret is required.');
        }

        $this->validateSignature($request, $secret);

        $content = $request->toArray();

        if (
            !isset($content['type'], $content['created_at'], $content['data'])
            || !\is_string($content['type'])
            || !\is_string($content['created_at'])
            || !\is_array($content['data'])
        ) {
            throw new RejectWebhookException(406, 'Payload is malformed.');
        }

        try {
            return $this->converter->convert($content);
        } catch (ParseException $e) {
            throw new RejectWebhookException(406, $e->getMessage(), $e);
        }
    }

    /**
     * The header is "t=TIMESTAMP,v1=SIGNATURE", where the signature is the hex HMAC-SHA256 of "TIMESTAMP.BODY" keyed with the endpoint secret.
     */
    private function validateSignature(Request $request, #[\SensitiveParameter] string $secret): void
    {
        $parts = [];
        foreach (explode(',', $request->headers->get('Groundmail-Signature', '')) as $part) {
            [$key, $value] = explode('=', trim($part), 2) + [1 => ''];
            $parts[$key] = $value;
        }

        $timestamp = $parts['t'] ?? '';
        $signature = $parts['v1'] ?? '';

        if (!ctype_digit($timestamp) || '' === $signature) {
            throw new RejectWebhookException(403, 'Invalid signature.');
        }

        if (abs(($this->clock?->now()->getTimestamp() ?? time()) - (int) $timestamp) > self::TIMESTAMP_TOLERANCE) {
            throw new RejectWebhookException(403, 'Timestamp is outside the allowed time window.');
        }

        if (!hash_equals(hash_hmac('sha256', $timestamp.'.'.$request->getContent(), $secret), $signature)) {
            throw new RejectWebhookException(403, 'Invalid signature.');
        }
    }
}
