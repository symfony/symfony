<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\SecurityBundle\Routing;

use Symfony\Component\Routing\RouteCollection;

/**
 * Loads the routes that the firewalls declare, such as their logout paths.
 *
 * @internal
 */
final class SecurityRouteLoader
{
    /**
     * @param iterable<callable(): RouteCollection> $loaders
     */
    public function __construct(
        private readonly iterable $loaders,
    ) {
    }

    public function __invoke(): RouteCollection
    {
        $collection = new RouteCollection();

        foreach ($this->loaders as $loader) {
            $collection->addCollection($loader());
        }

        return $collection;
    }
}
