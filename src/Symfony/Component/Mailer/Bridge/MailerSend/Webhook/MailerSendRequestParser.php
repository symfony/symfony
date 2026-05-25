<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mailer\Bridge\MailerSend\Webhook;

use Symfony\Component\HttpFoundation\ChainRequestMatcher;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestMatcher\IsJsonRequestMatcher;
use Symfony\Component\HttpFoundation\RequestMatcher\MethodRequestMatcher;
use Symfony\Component\HttpFoundation\RequestMatcherInterface;
use Symfony\Component\Mailer\Bridge\MailerSend\RemoteEvent\MailerSendPayloadConverter;
use Symfony\Component\RemoteEvent\Event\Mailer\AbstractMailerEvent;
use Symfony\Component\RemoteEvent\Exception\ParseException;
use Symfony\Component\Webhook\Client\AbstractRequestParser;
use Symfony\Component\Webhook\Exception\RejectWebhookException;

final class MailerSendRequestParser extends AbstractRequestParser
{
    /**
     * Fixed secret that is used by MailerSend when doing a webhook.test request.
     *
     * @see https://developers.mailersend.com/api/v1/webhooks.html#webhooks-overview
     */
    public const TEST_SECRET = 'test_Am3L1GuOIc4blLUuHqAPxxwkZaJyEk8G';

    public function __construct(
        private readonly MailerSendPayloadConverter $converter,
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
        $content = $request->toArray();

        if ('webhook.test' === ($content['type'] ?? null)) {
            $secret = self::TEST_SECRET;
        } elseif (!isset($content['type'], $content['data']['email']['message']['id'], $content['data']['email']['recipient']['email'])) {
            throw new RejectWebhookException(406, 'Payload is malformed.');
        }

        if ($secret) {
            if (!$request->headers->get('Signature')) {
                throw new RejectWebhookException(406, 'Signature is required.');
            }

            $this->validateSignature(
                $request->headers->get('Signature'),
                $request->getContent(),
                $secret,
            );

            if (self::TEST_SECRET === $secret) {
                throw new RejectWebhookException(202);
            }
        }

        try {
            return $this->converter->convert($content);
        } catch (ParseException $e) {
            throw new RejectWebhookException(406, $e->getMessage(), $e);
        }
    }

    private function validateSignature(string $signature, string $payload, #[\SensitiveParameter] string $secret): void
    {
        // see https://developers.mailersend.com/api/v1/webhooks.html#security
        $computedSignature = hash_hmac('sha256', $payload, $secret);

        if (!hash_equals($signature, $computedSignature)) {
            throw new RejectWebhookException(406, 'Signature is wrong.');
        }
    }
}
