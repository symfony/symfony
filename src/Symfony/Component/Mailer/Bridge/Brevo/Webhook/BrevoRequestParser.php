<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mailer\Bridge\Brevo\Webhook;

use Symfony\Component\HttpFoundation\ChainRequestMatcher;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestMatcher\IpsRequestMatcher;
use Symfony\Component\HttpFoundation\RequestMatcher\IsJsonRequestMatcher;
use Symfony\Component\HttpFoundation\RequestMatcher\MethodRequestMatcher;
use Symfony\Component\HttpFoundation\RequestMatcherInterface;
use Symfony\Component\Mailer\Bridge\Brevo\RemoteEvent\BrevoPayloadConverter;
use Symfony\Component\RemoteEvent\Event\Mailer\AbstractMailerEvent;
use Symfony\Component\RemoteEvent\Exception\ParseException;
use Symfony\Component\Webhook\Client\AbstractRequestParser;
use Symfony\Component\Webhook\Exception\RejectWebhookException;

final class BrevoRequestParser extends AbstractRequestParser
{
    public function __construct(
        private readonly BrevoPayloadConverter $converter,
    ) {
    }

    protected function getRequestMatcher(): RequestMatcherInterface
    {
        return new ChainRequestMatcher([
            new MethodRequestMatcher('POST'),
            new IsJsonRequestMatcher(),
            // https://developers.brevo.com/docs/how-to-use-webhooks#securing-your-webhooks
            // localhost is added for testing
            new IpsRequestMatcher(['185.107.232.1/24', '1.179.112.1/20', '127.0.0.1']),
        ]);
    }

    protected function doParse(Request $request, #[\SensitiveParameter] string $secret): ?AbstractMailerEvent
    {
        $content = $request->toArray();
        if (
            !isset($content['event'])
            || !isset($content['email'])
            || !isset($content['message-id'])
            || !isset($content['ts_event'])
        ) {
            throw new RejectWebhookException(406, 'Payload is malformed.');
        }

        try {
            return $this->converter->convert($content);
        } catch (ParseException $e) {
            throw new RejectWebhookException(406, $e->getMessage(), $e);
        }
    }
}
