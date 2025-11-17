<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mailer\Bridge\Mailchimp\Webhook;

use Symfony\Component\HttpFoundation\ChainRequestMatcher;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestMatcher\IsJsonRequestMatcher;
use Symfony\Component\HttpFoundation\RequestMatcher\MethodRequestMatcher;
use Symfony\Component\HttpFoundation\RequestMatcherInterface;
use Symfony\Component\Mailer\Bridge\Mailchimp\RemoteEvent\MailchimpPayloadConverter;
use Symfony\Component\RemoteEvent\Exception\ParseException;
use Symfony\Component\RemoteEvent\RemoteEvent;
use Symfony\Component\Webhook\Client\AbstractRequestParser;
use Symfony\Component\Webhook\Exception\RejectWebhookException;

final class MailchimpRequestParser extends AbstractRequestParser
{
    public function __construct(
        private readonly MailchimpPayloadConverter $converter,
    ) {
    }

    protected function getRequestMatcher(): RequestMatcherInterface
    {
        return new ChainRequestMatcher([
            new MethodRequestMatcher('POST'),
            new IsJsonRequestMatcher(),
        ]);
    }

    protected function doParse(Request $request, #[\SensitiveParameter] string $secret): RemoteEvent|array|null
    {
        $content = $request->toArray();
        if (!isset($content['mandrill_events'][0]['event'])
            || !isset($content['mandrill_events'][0]['msg'])
        ) {
            throw new RejectWebhookException(400, 'Payload malformed.');
        }

        $this->validateSignature($content, $secret, $request->getUri(), $request->headers->get('X-Mandrill-Signature'));

        try {
            return array_map($this->converter->convert(...), $content['mandrill_events']);
        } catch (ParseException $e) {
            throw new RejectWebhookException(406, $e->getMessage(), $e);
        }
    }

    /**
     * @see https://mailchimp.com/developer/transactional/guides/track-respond-activity-webhooks/#authenticating-webhook-requests
     */
    private function validateSignature(array $content, string $secret, string $webhookUrl, ?string $mandrillHeaderSignature): void
    {
        if (null === $mandrillHeaderSignature || false === isset($content['mandrill_events'])) {
            throw new RejectWebhookException(400, 'Signature is wrong.');
        }
        // First add url to signedData.
        $signedData = $webhookUrl;

        // When no params is set we know it's a test and we set the key to test.
        if ('[]' === $content['mandrill_events']) {
            $secret = 'test-webhook';
        }

        // Sort params and add to signed data.
        ksort($content);
        foreach ($content as $key => $value) {
            // Add keys and values.
            $signedData .= $key;
            $signedData .= \is_array($value) ? $this->stringifyArray($value) : $value;
        }

        if ($mandrillHeaderSignature !== base64_encode(hash_hmac('sha1', $signedData, $secret, true))) {
            throw new RejectWebhookException(400, 'Signature is wrong.');
        }
    }

    /**
     * Recursively converts an array to a string representation.
     *
     * @param array $array the array to be converted
     */
    private function stringifyArray(array $array): string
    {
        ksort($array);
        $result = '';
        foreach ($array as $key => $value) {
            $result .= $key;
            if (\is_array($value)) {
                $result .= $this->stringifyArray($value);
            } else {
                $result .= $value;
            }
        }

        return $result;
    }
}
