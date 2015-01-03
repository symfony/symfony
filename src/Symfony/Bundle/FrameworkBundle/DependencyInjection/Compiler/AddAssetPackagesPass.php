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
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

class AddAssetPackagesPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container)
    {
        if (!$container->hasDefinition('templating.asset.packages')) {
            return;
        }

        $defaultPackage = new Reference('templating.asset.default_package');
        $namedPackages = array();

        // tagged packages
        foreach ($container->findTaggedServiceIds('templating.asset_package') as $id => $attributes) {
            $name = isset($attributes['name']) ? $attributes['name'] : $id;
            $namedPackages[$name] = $package = new Reference($id);
        }

        $container->getDefinition('templating.asset.packages')
            ->replaceArgument(0, $defaultPackage)
            ->replaceArgument(1, $namedPackages)
        ;
    }
}
