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

        $currentBaseUrl = $context->getBaseUrl();
        $currentMethod = $context->getMethod();
        $currentHost = $context->getHost();
        $currentScheme = $context->getScheme();
        $currentHttpPort = $context->getHttpPort();
        $currentHttpsPort = $context->getHttpsPort();
        $currentPathInfo = $context->getPathInfo();
        $currentQueryString = $context->getQueryString();

        /* @var RouterContextStamp $contextStamp */
        $context
            ->setBaseUrl($contextStamp->getBaseUrl())
            ->setMethod($contextStamp->getMethod())
            ->setHost($contextStamp->getHost())
            ->setScheme($contextStamp->getScheme())
            ->setHttpPort($contextStamp->getHttpPort())
            ->setHttpsPort($contextStamp->getHttpsPort())
            ->setPathInfo($contextStamp->getPathInfo())
            ->setQueryString($contextStamp->getQueryString())
        ;

        try {
            return $stack->next()->handle($envelope, $stack);
        } finally {
            $context
                ->setBaseUrl($currentBaseUrl)
                ->setMethod($currentMethod)
                ->setHost($currentHost)
                ->setScheme($currentScheme)
                ->setHttpPort($currentHttpPort)
                ->setHttpsPort($currentHttpsPort)
                ->setPathInfo($currentPathInfo)
                ->setQueryString($currentQueryString)
            ;
        }
    }
}
