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

class TemplatingAssetHelperPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container)
    {
        if (!$container->hasDefinition('templating.helper.assets')) {
            return;
        }

        $assetsHelperDefinition = $container->getDefinition('templating.helper.assets');
        $args = $assetsHelperDefinition->getArguments();

        // add tagged packages
        $namedPackages = $args[1];
        foreach ($container->findTaggedServiceIds('templating.asset_package') as $id => $attributes) {
            $name = isset($attributes['name']) ? $attributes['name'] : $id;
            $namedPackages[$name] = $package = new Reference($id);
        }
        $assetsHelperDefinition->replaceArgument(1, $namedPackages);

        // fix helper scope
        $scope = $this->getPackageScope($container, $args[0]);
        foreach ($namedPackages as $package) {
            if ('request' === $this->getPackageScope($container, $package)) {
                $scope = 'request';
            }
        }
        $assetsHelperDefinition->setScope($scope);
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
