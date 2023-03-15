<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mailer\Bridge\Postmark\Webhook;

use Symfony\Component\HttpFoundation\ChainRequestMatcher;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestMatcher\IpsRequestMatcher;
use Symfony\Component\HttpFoundation\RequestMatcher\IsJsonRequestMatcher;
use Symfony\Component\HttpFoundation\RequestMatcher\MethodRequestMatcher;
use Symfony\Component\HttpFoundation\RequestMatcherInterface;
use Symfony\Component\Mailer\Bridge\Postmark\RemoteEvent\PostmarkPayloadConverter;
use Symfony\Component\Webhook\Client\AbstractRequestParser;
use Symfony\Component\Webhook\Exception\RejectWebhookException;
use Symfony\Component\RemoteEvent\Event\Mailer\AbstractMailerEvent;
use Symfony\Component\RemoteEvent\Exception\ParseException;

final class PostmarkRequestParser extends AbstractRequestParser
{
    public function __construct(
        private readonly PostmarkPayloadConverter $converter,
    ) {
    }

    protected function getRequestMatcher(): RequestMatcherInterface
    {
        return new ChainRequestMatcher([
            new MethodRequestMatcher('POST'),
            // https://postmarkapp.com/support/article/800-ips-for-firewalls#webhooks
            // localhost is added for testing
            new IpsRequestMatcher(['3.134.147.250', '50.31.156.6', '50.31.156.77', '18.217.206.57', '127.0.0.1']),
            new IsJsonRequestMatcher(),
        ]);
    }

    protected function doParse(Request $request, string $secret): ?AbstractMailerEvent
    {
        $payload = $request->toArray();
        if (
            !isset($payload['RecordType'])
            || !isset($payload['MessageID'])
            || !(isset($payload['Recipient']) || isset($payload['Email']))
            || !isset($payload['Metadata'])
            || !isset($payload['Tag'])
        ) {
            throw new RejectWebhookException(406, 'Payload is malformed.');
        }

        try {
            return $this->converter->convert($payload);
        } catch (ParseException $e) {
            throw new RejectWebhookException(406, $e->getMessage(), $e);
        }
    }
}
