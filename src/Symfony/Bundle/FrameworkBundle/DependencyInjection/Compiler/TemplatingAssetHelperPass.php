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

class TemplatingAssetHelperPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container)
    {
        if (!$container->hasDefinition('templating.helper.assets')) {
            return;
        }

        if (!$container->hasDefinition('templating.asset.packages')) {
            return;
        }

        $packages = $container->getDefinition('templating.asset.packages');

        $container->getDefinition('templating.helper.assets')
            ->setScope($packages->getScope())
            ->replaceArgument(0, $packages->getArgument(0))
            ->replaceArgument(1, $packages->getArgument(1))
        ;
    }
}
