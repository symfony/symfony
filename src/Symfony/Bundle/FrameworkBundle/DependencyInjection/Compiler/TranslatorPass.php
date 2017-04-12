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

use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\Compiler\ServiceLocatorTagPass;

class TranslatorPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container)
    {
        if (!$container->hasDefinition('translator.default')) {
            return;
        }

        $loaders = array();
        $loaderRefs = array();
        foreach ($container->findTaggedServiceIds('translation.loader', true) as $id => $attributes) {
            $loaderRefs[$id] = new Reference($id);
            $loaders[$id][] = $attributes[0]['alias'];
            if (isset($attributes[0]['legacy-alias'])) {
                $loaders[$id][] = $attributes[0]['legacy-alias'];
            }
        }

        if ($container->hasDefinition('translation.loader')) {
            $definition = $container->getDefinition('translation.loader');
            foreach ($loaders as $id => $formats) {
                foreach ($formats as $format) {
                    $definition->addMethodCall('addLoader', array($format, $loaderRefs[$id]));
                }
            }
        }

        $container
            ->findDefinition('translator.default')
            ->replaceArgument(0, ServiceLocatorTagPass::register($container, $loaderRefs))
            ->replaceArgument(3, $loaders)
        ;
    }
}
