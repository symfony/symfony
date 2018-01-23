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

use Symfony\Component\Routing\Matcher\RedirectableUrlMatcherInterface;
use Symfony\Component\Routing\Matcher\StaticUrlMatcher;

/**
 * @author Fabien Potencier <fabien@symfony.com>
 */
class RedirectableStaticUrlMatcher extends StaticUrlMatcher implements RedirectableUrlMatcherInterface
{
    /**
     * {@inheritdoc}
     */
    public function redirect($path, $route, $scheme = null)
    {
        return array(
            '_controller' => 'Symfony\\Bundle\\FrameworkBundle\\Controller\\RedirectController::urlRedirectAction',
            'path' => $path,
            'permanent' => true,
            'scheme' => $scheme,
            'httpPort' => $this->context->getHttpPort(),
            'httpsPort' => $this->context->getHttpsPort(),
            '_route' => $route,
        );
    }
}
