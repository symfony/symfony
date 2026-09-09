<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\PropertyInfo\DependencyInjection;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Removes the caching extractor when CacheBundle is not registered.
 *
 * Its pool is cleared along with the other system pools, which that bundle owns.
 *
 * @internal
 */
class RemovePropertyInfoCachePass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition('cache.system')) {
            $container->removeDefinition('cache.property_info');
            $container->removeDefinition('property_info.cache');
        }
    }
}
