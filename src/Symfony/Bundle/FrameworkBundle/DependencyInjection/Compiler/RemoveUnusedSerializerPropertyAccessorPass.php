<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Removes the serializer services that need the property accessor when it is not registered.
 *
 * @internal
 */
class RemoveUnusedSerializerPropertyAccessorPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if ($container->has('property_accessor')) {
            return;
        }

        $container->removeAlias('serializer.property_accessor');
        $container->removeDefinition('serializer.normalizer.object');
        $container->removeDefinition('serializer.denormalizer.unwrapping');
    }
}
