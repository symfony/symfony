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

/**
 * RedirectableUrlMatcherInterface knows how to redirect the user.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
interface RedirectableUrlMatcherInterface
{
    /**
     * Redirects the user to another URL.
     *
     * As the Routing component does not know know to redirect the user,
     * the default implementation throw an exception.
     *
     * Override this method to implement your own logic.
     *
     * If you are using a Dumper, don't forget to change the default base.
     *
     * @param string $pathinfo The path info to redirect to.
     * @param string $route    The route that matched
     *
     * @return array An array of parameters
     */
    function redirect($pathinfo, $route);
}
