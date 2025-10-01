<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mailer\Bridge\Mailomat\Webhook;

use Symfony\Component\HttpFoundation\ChainRequestMatcher;
use Symfony\Component\HttpFoundation\HeaderBag;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestMatcher\HeaderRequestMatcher;
use Symfony\Component\HttpFoundation\RequestMatcher\IsJsonRequestMatcher;
use Symfony\Component\HttpFoundation\RequestMatcher\MethodRequestMatcher;
use Symfony\Component\HttpFoundation\RequestMatcherInterface;
use Symfony\Component\Mailer\Bridge\Mailomat\RemoteEvent\MailomatPayloadConverter;
use Symfony\Component\Mailer\Exception\InvalidArgumentException;
use Symfony\Component\RemoteEvent\Event\Mailer\AbstractMailerEvent;
use Symfony\Component\RemoteEvent\Exception\ParseException;
use Symfony\Component\Webhook\Client\AbstractRequestParser;
use Symfony\Component\Webhook\Exception\RejectWebhookException;

final class MailomatRequestParser extends AbstractRequestParser
{
    private const HEADER_EVENT = 'X-MOM-Webhook-Event';
    private const HEADER_ID = 'X-MOM-Webhook-Id';
    private const HEADER_TIMESTAMP = 'X-MOM-Webhook-Timestamp';
    private const HEADER_SIGNATURE = 'X-MOM-Webhook-Signature';

    public function __construct(
        private readonly MailomatPayloadConverter $converter,
    ) {
    }

    protected function getRequestMatcher(): RequestMatcherInterface
    {
        return new ChainRequestMatcher([
            new MethodRequestMatcher('POST'),
            new IsJsonRequestMatcher(),
            new HeaderRequestMatcher([
                self::HEADER_EVENT,
                self::HEADER_TIMESTAMP,
                self::HEADER_ID,
                self::HEADER_SIGNATURE,
            ]),
        ]);
    }

    protected function doParse(Request $request, #[\SensitiveParameter] string $secret): ?AbstractMailerEvent
    {
        if (!$secret) {
            throw new InvalidArgumentException('A non-empty secret is required.');
        }

        $content = $request->toArray();

        if (
            !isset($content['id'])
            || !isset($content['eventType'])
            || !isset($content['occurredAt'])
            || !isset($content['messageId'])
            || !isset($content['recipient'])
        ) {
            throw new RejectWebhookException(406, 'Payload is malformed.');
        }

        $this->validateSignature($request->headers, $secret);

        try {
            return $this->converter->convert($content);
        } catch (ParseException $e) {
            throw new RejectWebhookException(406, $e->getMessage(), $e);
        }
    }

    private function validateSignature(HeaderBag $headers, #[\SensitiveParameter] string $secret): void
    {
        // see https://api.mailomat.swiss/docs/#tag/webhook-security
        $data = implode('.', [$headers->get(self::HEADER_ID), $headers->get(self::HEADER_EVENT), $headers->get(self::HEADER_TIMESTAMP)]);

        [$algo, $signature] = explode('=', $headers->get(self::HEADER_SIGNATURE));
        if (!hash_equals(hash_hmac($algo, $data, $secret), $signature)) {
            throw new RejectWebhookException(406, 'Signature is wrong.');
        }
    }
}
