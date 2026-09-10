<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Routing\Attribute;

/**
 * Marks a service as a route loader, so that its methods can be referenced from a routing resource.
 *
 *     #[AsRouteLoader]
 *     class SomeRouteLoader
 *     {
 *         public function loadRoutes(): RouteCollection
 *         {
 *             // ...
 *         }
 *     }
 *
 * Then reference it from the routing configuration:
 *
 *     some_routes:
 *         resource: 'some_route_loader::loadRoutes'
 *         type: service
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
class AsRouteLoader
{
}
