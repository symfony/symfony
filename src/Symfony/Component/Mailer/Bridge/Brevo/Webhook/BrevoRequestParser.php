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
    // https://help.brevo.com/hc/en-us/articles/15127404548498-Brevo-IP-ranges-List-of-publicly-exposed-services
    public const PROVIDER_IPS = ['1.179.112.0/20', '172.246.240.0/20'];

    public function __construct(
        private readonly BrevoPayloadConverter $converter,
        private readonly array $allowedIPs = self::PROVIDER_IPS,
    ) {
    }

    protected function getRequestMatcher(): RequestMatcherInterface
    {
        return new ChainRequestMatcher([
            new MethodRequestMatcher('POST'),
            new IsJsonRequestMatcher(),
            new IpsRequestMatcher($this->allowedIPs),
        ]);
    }

    protected function doParse(Request $request, #[\SensitiveParameter] string $secret): ?AbstractMailerEvent
    {
        if ($secret && !hash_equals('Basic '.base64_encode($secret), $request->headers->get('Authorization', ''))) {
            throw new RejectWebhookException(403, 'Invalid credentials.');
        }

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
