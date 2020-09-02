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

@trigger_error(sprintf('The "%s" class is deprecated since Symfony 4.3.', RedirectableUrlMatcher::class), \E_USER_DEPRECATED);

use Symfony\Component\Routing\Matcher\RedirectableUrlMatcher as BaseMatcher;

/**
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @deprecated since Symfony 4.3
 */
class RedirectableUrlMatcher extends BaseMatcher
{
    /**
     * Redirects the user to another URL.
     *
     * @param string $path   The path info to redirect to
     * @param string $route  The route that matched
     * @param string $scheme The URL scheme (null to keep the current one)
     *
     * @return array An array of parameters
     */
    public function redirect($path, $route, $scheme = null)
    {
        return [
            '_controller' => 'Symfony\\Bundle\\FrameworkBundle\\Controller\\RedirectController::urlRedirectAction',
            'path' => $path,
            'permanent' => true,
            'scheme' => $scheme,
            'httpPort' => $this->context->getHttpPort(),
            'httpsPort' => $this->context->getHttpsPort(),
            '_route' => $route,
        ];
    }
}
