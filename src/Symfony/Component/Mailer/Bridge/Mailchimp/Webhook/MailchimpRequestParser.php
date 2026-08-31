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
use Symfony\Component\HttpFoundation\RequestMatcher\MethodRequestMatcher;
use Symfony\Component\HttpFoundation\RequestMatcherInterface;
use Symfony\Component\Mailer\Bridge\Mailchimp\RemoteEvent\MailchimpPayloadConverter;
use Symfony\Component\Mailer\Exception\InvalidArgumentException;
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
        ]);
    }

    protected function doParse(Request $request, #[\SensitiveParameter] string $secret): RemoteEvent|array|null
    {
        if (!$secret) {
            throw new InvalidArgumentException('A non-empty secret is required.');
        }

        $content = $request->request->all();
        if (!\is_string($content['mandrill_events'] ?? null)) {
            throw new RejectWebhookException(400, 'Payload malformed.');
        }

        // Mailchimp sends an empty array to verify the webhook URL is reachable.
        if ([] === $events = json_decode($content['mandrill_events'], true)) {
            $this->validateSignature($content, $secret, $this->getWebhookUrl($request), $request->headers->get('X-Mandrill-Signature'));

            return null;
        }

        if (!isset($events[0]['event']) || !isset($events[0]['msg'])) {
            throw new RejectWebhookException(400, 'Payload malformed.');
        }

        $this->validateSignature($content, $secret, $this->getWebhookUrl($request), $request->headers->get('X-Mandrill-Signature'));

        try {
            return array_map($this->converter->convert(...), $events);
        } catch (ParseException $e) {
            throw new RejectWebhookException(406, $e->getMessage(), $e);
        }
    }

    /**
     * Mandrill signs the webhook URL as configured, so the query string must be used as sent, not normalized.
     */
    private function getWebhookUrl(Request $request): string
    {
        $url = $request->getSchemeAndHttpHost().$request->getBaseUrl().$request->getPathInfo();
        if ('' !== ($queryString = $request->server->get('QUERY_STRING', '')) && null !== $queryString) {
            $url .= '?'.$queryString;
        }

        return $url;
    }

    /**
     * @see https://mailchimp.com/developer/transactional/guides/track-respond-activity-webhooks/#authenticating-webhook-requests
     */
    private function validateSignature(array $content, #[\SensitiveParameter] string $secret, string $webhookUrl, ?string $mandrillHeaderSignature): void
    {
        if (null === $mandrillHeaderSignature || !isset($content['mandrill_events'])) {
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

        if (!hash_equals(base64_encode(hash_hmac('sha1', $signedData, $secret, true)), $mandrillHeaderSignature)) {
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
