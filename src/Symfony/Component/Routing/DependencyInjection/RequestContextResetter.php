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
 * Restores the parameters that the request context had when the container created it.
 *
 * @internal
 */
final class RequestContextResetter
{
    private RequestContext $context;
    private array $parameters;

    public function __invoke(RequestContext $context): void
    {
        $this->context = $context;
        $this->parameters = $context->getParameters();
    }

    public function reset(): void
    {
        $this->context->setParameters($this->parameters);
    }
}
