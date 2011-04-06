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

use Symfony\Component\Routing\Matcher\UrlMatcher;
use Symfony\Component\Routing\Matcher\RedirectableUrlMatcherInterface;

/**
 * @author Fabien Potencier <fabien@symfony.com>
 */
class RedirectableUrlMatcher extends UrlMatcher implements RedirectableUrlMatcherInterface
{
    public function redirect($pathinfo, $route)
    {
        return array(
            '_controller' => 'Symfony\\Bundle\\FrameworkBundle\\Controller\\RedirectController::urlRedirectAction',
            'url'         => $this->context['base_url'].$pathinfo,
            'permanent'   => true,
            '_route'      => $route,
        );
    }
}
