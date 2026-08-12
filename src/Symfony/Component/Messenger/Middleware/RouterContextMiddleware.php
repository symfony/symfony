<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Middleware;

use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Stamp\ConsumedByWorkerStamp;
use Symfony\Component\Messenger\Stamp\RouterContextStamp;
use Symfony\Component\Routing\RequestContextAwareInterface;

/**
 * Restore the Router context when processing the message.
 *
 * @author Jérémy Derussé <jeremy@derusse.com>
 */
class RouterContextMiddleware implements MiddlewareInterface
{
    public function __construct(
        private RequestContextAwareInterface $router,
    ) {
    }

    public function handle(Envelope $envelope, StackInterface $stack): Envelope
    {
        $context = $this->router->getContext();

        if (!$envelope->last(ConsumedByWorkerStamp::class) || !$contextStamp = $envelope->last(RouterContextStamp::class)) {
            $envelope = $envelope->with(new RouterContextStamp(
                $context->getBaseUrl(),
                $context->getMethod(),
                $context->getHost(),
                $context->getScheme(),
                $context->getHttpPort(),
                $context->getHttpsPort(),
                $context->getPathInfo(),
                $context->getQueryString()
            ));

            return $stack->next()->handle($envelope, $stack);
        }

        return $context->runWith(
            static fn () => $stack->next()->handle($envelope, $stack),
            baseUrl: $contextStamp->getBaseUrl(),
            method: $contextStamp->getMethod(),
            host: $contextStamp->getHost(),
            scheme: $contextStamp->getScheme(),
            httpPort: $contextStamp->getHttpPort(),
            httpsPort: $contextStamp->getHttpsPort(),
            pathInfo: $contextStamp->getPathInfo(),
            queryString: $contextStamp->getQueryString(),
        );
    }
}
