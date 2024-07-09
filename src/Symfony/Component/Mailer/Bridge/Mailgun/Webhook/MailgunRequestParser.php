<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mailer\Bridge\Mailgun\Webhook;

use Symfony\Component\HttpFoundation\ChainRequestMatcher;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestMatcher\IsJsonRequestMatcher;
use Symfony\Component\HttpFoundation\RequestMatcher\MethodRequestMatcher;
use Symfony\Component\HttpFoundation\RequestMatcherInterface;
use Symfony\Component\Mailer\Bridge\Mailgun\RemoteEvent\MailgunPayloadConverter;
use Symfony\Component\Mailer\Exception\InvalidArgumentException;
use Symfony\Component\RemoteEvent\Event\Mailer\AbstractMailerEvent;
use Symfony\Component\RemoteEvent\Exception\ParseException;
use Symfony\Component\Webhook\Client\AbstractRequestParser;
use Symfony\Component\Webhook\Exception\RejectWebhookException;

final class MailgunRequestParser extends AbstractRequestParser
{
    public function __construct(
        private readonly MailgunPayloadConverter $converter,
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
        if (!$secret) {
            throw new InvalidArgumentException('A non-empty secret is required.');
        }

        $content = $request->toArray();
        if (
            !isset($content['signature']['timestamp'])
            || !isset($content['signature']['token'])
            || !isset($content['signature']['signature'])
            || !isset($content['event-data']['event'])
        ) {
            throw new RejectWebhookException(406, 'Payload is malformed.');
        }

        $this->validateSignature($content['signature'], $secret);

        try {
            return $this->converter->convert($content['event-data']);
        } catch (ParseException $e) {
            throw new RejectWebhookException(406, $e->getMessage(), $e);
        }
    }

    private function validateSignature(array $signature, #[\SensitiveParameter] string $secret): void
    {
        // see https://documentation.mailgun.com/en/latest/user_manual.html#webhooks-1
        if (!hash_equals($signature['signature'], hash_hmac('sha256', $signature['timestamp'].$signature['token'], $secret))) {
            throw new RejectWebhookException(406, 'Signature is wrong.');
        }
    }
}
