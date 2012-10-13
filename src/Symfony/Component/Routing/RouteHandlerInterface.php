<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Routing;

use Symfony\Component\Routing\Route;

/**
 * @author Jean-François Simon <contact@jfsimon.fr>
 */
interface RouteHandlerInterface
{
    /**
     * Updates the route before its compilation.
     *
     * @param Route $route
     */
    function updateBeforeCompilation(Route $route);

    /**
     * Checks route for an exception during matching.
     *
     * @param Route $route
     */
    function checkMatcherExceptions(Route $route);

    /**
     * Updates matched parameters.
     *
     * @param Route $route
     * @param array $parameters
     *
     * @return array
     */
    function updateMatchedParameters(Route $route, array $parameters);
}
