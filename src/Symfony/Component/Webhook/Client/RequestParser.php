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

use Symfony\Component\HttpFoundation\ChainRequestMatcher;
use Symfony\Component\HttpFoundation\HeaderBag;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestMatcher\IsJsonRequestMatcher;
use Symfony\Component\HttpFoundation\RequestMatcher\MethodRequestMatcher;
use Symfony\Component\HttpFoundation\RequestMatcherInterface;
use Symfony\Component\RemoteEvent\RemoteEvent;
use Symfony\Component\Webhook\Exception\RejectWebhookException;

/**
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @experimental in 6.3
 */
class RequestParser extends AbstractRequestParser
{
    public function __construct(
        private readonly string $algo = 'sha256',
        private readonly string $signatureHeaderName = 'Webhook-Signature',
        private readonly string $eventHeaderName = 'Webhook-Event',
        private readonly string $idHeaderName = 'Webhook-Id',
    ) {
    }

    protected function getRequestMatcher(): RequestMatcherInterface
    {
        return new ChainRequestMatcher([
            new MethodRequestMatcher('POST'),
            new IsJsonRequestMatcher(),
        ]);
    }

    protected function doParse(Request $request, string $secret): RemoteEvent
    {
        $body = $request->toArray();

        foreach ([$this->signatureHeaderName, $this->eventHeaderName, $this->idHeaderName] as $header) {
            if (!$request->headers->has($header)) {
                throw new RejectWebhookException(406, sprintf('Missing "%s" HTTP request signature header.', $header));
            }
        }

        $this->validateSignature($request->headers, $request->getContent(), $secret);

        return new RemoteEvent(
            $request->headers->get($this->eventHeaderName),
            $request->headers->get($this->idHeaderName),
            $body
        );
    }

    private function validateSignature(HeaderBag $headers, string $body, $secret): void
    {
        $signature = $headers->get($this->signatureHeaderName);
        $event = $headers->get($this->eventHeaderName);
        $id = $headers->get($this->idHeaderName);

        if (!hash_equals($signature, $this->algo.'='.hash_hmac($this->algo, $event.$id.$body, $secret))) {
            throw new RejectWebhookException(406, 'Signature is wrong.');
        }
    }
}
