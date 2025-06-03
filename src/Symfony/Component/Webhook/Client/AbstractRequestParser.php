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

    /**
     * @param Request|null $request The original request that was received by the webhook controller
     */
    public function createSuccessfulResponse(/* ?Request $request = null */): Response
    {
        return new Response('', 202);
    }

    /**
     * @param Request|null $request The original request that was received by the webhook controller
     */
    public function createRejectedResponse(string $reason/* , ?Request $request = null */): Response
    {
        return new Response($reason, 406);
    }

    abstract protected function getRequestMatcher(): RequestMatcherInterface;

    abstract protected function doParse(Request $request, #[\SensitiveParameter] string $secret): RemoteEvent|array|null;

    protected function validate(Request $request): void
    {
        if (!$this->getRequestMatcher()->matches($request)) {
            throw new RejectWebhookException(406, 'Request does not match.');
        }
    }
}
