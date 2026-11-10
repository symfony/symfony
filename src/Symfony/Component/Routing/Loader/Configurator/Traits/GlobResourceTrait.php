<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Routing\Loader\Configurator\Traits;

use Symfony\Component\Routing\RouteCollection;

/**
 * @internal
 */
trait GlobResourceTrait
{
    /**
     * @internal
     */
    public function trackGlobResource(mixed $resource, RouteCollection $collection): void
    {
        if (!\is_string($resource) || \strlen($resource) === strcspn($resource, '*?{[') || str_contains($resource, "\n")) {
            return;
        }

        foreach ($this->glob($resource, false, $globResource, true) as $_) {
            break;
        }

        foreach (\is_array($globResource) ? $globResource : [$globResource] as $r) {
            $collection->addResource($r);
        }
    }
}
