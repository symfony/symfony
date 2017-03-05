<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\ContainerBuilder;

class RemoveInvalidAutoregisteredPass implements CompilerPassInterface
{
    /**
     * {@inheritdoc}
     */
    public function process(ContainerBuilder $container)
    {
        foreach ($container->findTaggedServiceIds('kernel.autoregistered') as $id => $tags) {
            $r = $container->getReflectionClass($id, true);
            if (!$r || !$r->isInstantiable()) {
                $container->removeDefinition($id);
            }
        }
    }
}
