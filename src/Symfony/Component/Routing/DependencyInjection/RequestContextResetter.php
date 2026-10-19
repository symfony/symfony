<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Routing\DependencyInjection;

use Symfony\Component\Routing\RequestContext;

/**
 * Restores the state that the request context had when the container created it.
 *
 * @internal
 */
final class RequestContextResetter
{
    private \Closure $reset;

    public function __invoke(RequestContext $context): void
    {
        $initialContext = clone $context;

        // the properties are written directly, which is several times faster than going through the getters and setters
        $this->reset = \Closure::bind(static function () use ($context, $initialContext) {
            $context->baseUrl = $initialContext->baseUrl;
            $context->pathInfo = $initialContext->pathInfo;
            $context->method = $initialContext->method;
            $context->host = $initialContext->host;
            $context->scheme = $initialContext->scheme;
            $context->httpPort = $initialContext->httpPort;
            $context->httpsPort = $initialContext->httpsPort;
            $context->queryString = $initialContext->queryString;
            $context->parameters = $initialContext->parameters;
        }, null, RequestContext::class);
    }

    public function reset(): void
    {
        ($this->reset)();
    }
}
