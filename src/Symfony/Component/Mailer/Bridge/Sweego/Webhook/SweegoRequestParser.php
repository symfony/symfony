<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mailer\Bridge\Sweego\Webhook;

use Symfony\Component\HttpFoundation\ChainRequestMatcher;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestMatcher\HeaderRequestMatcher;
use Symfony\Component\HttpFoundation\RequestMatcher\IsJsonRequestMatcher;
use Symfony\Component\HttpFoundation\RequestMatcher\MethodRequestMatcher;
use Symfony\Component\HttpFoundation\RequestMatcherInterface;
use Symfony\Component\Mailer\Bridge\Sweego\RemoteEvent\SweegoPayloadConverter;
use Symfony\Component\RemoteEvent\Event\Mailer\AbstractMailerEvent;
use Symfony\Component\RemoteEvent\Exception\ParseException;
use Symfony\Component\Webhook\Client\AbstractRequestParser;
use Symfony\Component\Webhook\Exception\RejectWebhookException;

final class SweegoRequestParser extends AbstractRequestParser
{
    public function __construct(
        private readonly SweegoPayloadConverter $converter,
    ) {
    }

    protected function getRequestMatcher(): RequestMatcherInterface
    {
        return new ChainRequestMatcher([
            new MethodRequestMatcher('POST'),
            new IsJsonRequestMatcher(),
            new HeaderRequestMatcher(['webhook-id', 'webhook-timestamp', 'webhook-signature']),
        ]);
    }

    protected function doParse(Request $request, #[\SensitiveParameter] string $secret): ?AbstractMailerEvent
    {
        $content = $request->toArray();

        if (
            !isset($content['event_type'])
            || !isset($content['timestamp'])
            || !isset($content['headers'])
            || !isset($content['headers']['x-transaction-id'])
            || !isset($content['recipient'])
        ) {
            throw new RejectWebhookException(406, 'Payload is malformed.');
        }

        $this->validateSignature($request, $secret);

        try {
            return $this->converter->convert($content);
        } catch (ParseException $e) {
            throw new RejectWebhookException(406, $e->getMessage(), $e);
        }
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
