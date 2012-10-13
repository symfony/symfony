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

/**
 * RouteHandlerAwareInterface
 *
 * @author Jean-François Simon <contact@jfsimon.fr>
 */
interface RouteHandlerAwareInterface
{
    /**
     * Adds a route handler.
     *
     * @param RouteHandlerInterface $handler
     */
    public function addRouteHandler(RouteHandlerInterface $handler);
}
