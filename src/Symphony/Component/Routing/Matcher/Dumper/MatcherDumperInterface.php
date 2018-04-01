<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\Routing\Matcher\Dumper;

use Symphony\Component\Routing\RouteCollection;

/**
 * MatcherDumperInterface is the interface that all matcher dumper classes must implement.
 *
 * @author Fabien Potencier <fabien@symphony.com>
 */
interface MatcherDumperInterface
{
    /**
     * Dumps a set of routes to a string representation of executable code
     * that can then be used to match a request against these routes.
     *
     * @param array $options An array of options
     *
     * @return string Executable code
     */
    public function dump(array $options = array());

    /**
     * Gets the routes to dump.
     *
     * @return RouteCollection A RouteCollection instance
     */
    public function getRoutes();
}
