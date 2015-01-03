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

        // fix helper scope
        $scope = $this->getPackageScope($container, $defaultPackage);
        foreach ($namedPackages as $package) {
            if ('request' === $this->getPackageScope($container, $package)) {
                $scope = 'request';
            }
        }

        $container->getDefinition('templating.asset.packages')
            ->setScope($scope)
            ->replaceArgument(0, $defaultPackage)
            ->replaceArgument(1, $namedPackages)
        ;
    }

    private function getPackageScope(ContainerBuilder $container, $package)
    {
        if ($package instanceof Reference) {
            return $container->findDefinition((string) $package)->getScope();
        }

        if ($package instanceof Definition) {
            return $package->getScope();
        }
    }
}
