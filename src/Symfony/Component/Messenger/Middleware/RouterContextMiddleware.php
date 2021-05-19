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
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\RequestContextAwareInterface;

/**
 * Restore the Router context when processing the message.
 */
class RouterContextMiddleware implements MiddlewareInterface
{
    private $router;

    public function __construct(RequestContextAwareInterface $router)
    {
        $this->router = $router;
    }

    public function handle(Envelope $envelope, StackInterface $stack): Envelope
    {
        if (!$envelope->last(ConsumedByWorkerStamp::class) || !$contextStamp = $envelope->last(RouterContextStamp::class)) {
            $context = $this->router->getContext();
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

        $currentContext = $this->router->getContext();

        /* @var RouterContextStamp $contextStamp */
        $this->router->setContext(new RequestContext(
            $contextStamp->getBaseUrl(),
            $contextStamp->getMethod(),
            $contextStamp->getHost(),
            $contextStamp->getScheme(),
            $contextStamp->getHttpPort(),
            $contextStamp->getHttpsPort(),
            $contextStamp->getPathInfo(),
            $contextStamp->getQueryString()
        ));

        try {
            return $stack->next()->handle($envelope, $stack);
        } finally {
            $this->router->setContext($currentContext);
        }
    }
}
