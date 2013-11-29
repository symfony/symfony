<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Routing\Generator;

/**
 * An object that is able to find the route from its name
 *
 * @author Grégoire Passault <g.passault@gmail.com>
 */
interface RouteResolverInterface
{
    /**
     * Getting a route.
     *
     * @param $name The route name
     *
     * @return UrlGeneratorRoute A route usable by the url generator
     */
    public function getRoute($name);
}
