<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Routing;

use Symfony\Component\Routing\RequestContext;

/**
 * Restores the state that the request context had when the container created it.
 *
 * @internal
 */
final class RequestContextResetter
{
    private RequestContext $context;
    private RequestContext $initialContext;

    public function __invoke(RequestContext $context): void
    {
        $this->context = $context;
        $this->initialContext = clone $context;
    }

    public function reset(): void
    {
        $this->context
            ->setBaseUrl($this->initialContext->getBaseUrl())
            ->setPathInfo($this->initialContext->getPathInfo())
            ->setMethod($this->initialContext->getMethod())
            ->setHost($this->initialContext->getHost())
            ->setScheme($this->initialContext->getScheme())
            ->setHttpPort($this->initialContext->getHttpPort())
            ->setHttpsPort($this->initialContext->getHttpsPort())
            ->setQueryString($this->initialContext->getQueryString())
            ->setParameters($this->initialContext->getParameters());
    }
}
