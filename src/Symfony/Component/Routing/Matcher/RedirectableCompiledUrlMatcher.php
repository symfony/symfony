<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Routing\Matcher;

use Symfony\Component\Routing\Controller\RedirectController;

/**
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @internal
 */
class RedirectableCompiledUrlMatcher extends CompiledUrlMatcher implements RedirectableUrlMatcherInterface
{
    public function redirect(string $path, string $route, ?string $scheme = null): array
    {
        return [
            '_controller' => RedirectController::class.'::urlRedirectAction',
            'path' => $path,
            'permanent' => true,
            'scheme' => $scheme,
            'httpPort' => $this->context->getHttpPort(),
            'httpsPort' => $this->context->getHttpsPort(),
            '_route' => $route,
            '_route_mapping' => [],
        ];
    }
}
