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

use Symfony\Component\Config\Resource\GlobResource;
use Symfony\Component\Routing\RouteCollection;

/**
 * @internal
 */
trait GlobResourceTrait
{
    /**
     * @internal
     */
    protected function trackGlobResource(GlobResource|array $resource, mixed $imported): mixed
    {
        $imported ??= new RouteCollection();
        $collection = \is_array($imported) ? $imported[0] : $imported;

        if ($collection instanceof RouteCollection) {
            foreach (\is_array($resource) ? $resource : [$resource] as $r) {
                $collection->addResource($r);
            }
        }

        return $imported;
    }
}
