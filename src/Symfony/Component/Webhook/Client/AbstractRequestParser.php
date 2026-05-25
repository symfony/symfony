<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Webhook\Client;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestMatcherInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RemoteEvent\RemoteEvent;
use Symfony\Component\Webhook\Exception\RejectWebhookException;

/**
 * @author Fabien Potencier <fabien@symfony.com>
 */
abstract class AbstractRequestParser implements RequestParserInterface
{
    public function parse(Request $request, #[\SensitiveParameter] string $secret): RemoteEvent|array|null
    {
        $this->validate($request);

        return $this->doParse($request, $secret);
    }

    public function createSuccessfulResponse(?Request $request = null): Response
    {
        return new Response('', 202);
    }

    public function createRejectedResponse(string $reason, ?Request $request = null): Response
    {
        return new Response($reason, 406);
    }

    abstract protected function getRequestMatcher(): RequestMatcherInterface;

    /**
     * Parses and authenticates the request, returning the resulting RemoteEvent(s).
     *
     * When the protocol requires it, implementations are responsible for verifying
     * the request's authenticity (typically by comparing an HMAC of the raw body
     * against a header value using hash_equals()) and for any replay protection
     * (e.g. timestamp window). They must throw RejectWebhookException on any
     * verification failure.
     *
     * The $request argument has already been matched by getRequestMatcher().
     *
     * @return RemoteEvent|RemoteEvent[]|null Returns null when the webhook must be ignored
     *
     * @throws RejectWebhookException On signature mismatch, malformed payload, replay, or unsupported event
     */
    abstract protected function doParse(Request $request, #[\SensitiveParameter] string $secret): RemoteEvent|array|null;

    protected function validate(Request $request): void
    {
        if (!$this->getRequestMatcher()->matches($request)) {
            throw new RejectWebhookException(406, 'Request does not match.');
        }
    }
}
