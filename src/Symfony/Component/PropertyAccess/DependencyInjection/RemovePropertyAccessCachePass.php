<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\PropertyAccess\DependencyInjection;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Removes the cache pool of the property accessor when CacheBundle is not registered.
 *
 * The pool is cleared along with the other system pools, which that bundle owns.
 *
 * @internal
 */
class RemovePropertyAccessCachePass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition('cache.system')) {
            $container->removeDefinition('cache.property_access');
        }
    }
}
