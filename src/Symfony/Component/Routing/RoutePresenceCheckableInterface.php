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
 * Interface for checking a route presence.
 *
 * @author Oleg Voronkovich <oleg-voronkovich@yandex.ru>
 */
interface RoutePresenceCheckableInterface
{
    /**
     * Returns whether a route with the given name exists.
     *
     * @param string $name The route name
     *
     * @return bool
     */
    public function hasRoute($name);
}
