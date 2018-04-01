<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\Routing;

use Symphony\Component\Routing\Generator\UrlGeneratorInterface;
use Symphony\Component\Routing\Matcher\UrlMatcherInterface;

/**
 * RouterInterface is the interface that all Router classes must implement.
 *
 * This interface is the concatenation of UrlMatcherInterface and UrlGeneratorInterface.
 *
 * @author Fabien Potencier <fabien@symphony.com>
 */
interface RouterInterface extends UrlMatcherInterface, UrlGeneratorInterface
{
    /**
     * Gets the RouteCollection instance associated with this Router.
     *
     * @return RouteCollection A RouteCollection instance
     */
    public function getRouteCollection();
}
