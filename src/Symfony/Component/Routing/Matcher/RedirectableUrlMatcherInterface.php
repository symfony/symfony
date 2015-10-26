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

@trigger_error('The '.__NAMESPACE__.'\RedirectableUrlMatcherInterface is deprecated since version 2.8 and will be removed in 3.0. Extend RedirectableRequestMatcher instead.', E_USER_DEPRECATED);

/**
 * RedirectableUrlMatcherInterface knows how to redirect the user.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @deprecated since version 2.8, to be removed in 3.0.
 *             Extend {@link Symfony\Component\Routing\Matcher\RedirectableRequestMatcher} instead.
 */
interface RedirectableUrlMatcherInterface
{
    /**
     * Redirects the user to another URL.
     *
     * @param string      $path   The path info to redirect to.
     * @param string      $route  The route name that matched
     * @param string|null $scheme The URL scheme (null to keep the current one)
     *
     * @return array An array of parameters
     */
    public function redirect($path, $route, $scheme = null);
}
