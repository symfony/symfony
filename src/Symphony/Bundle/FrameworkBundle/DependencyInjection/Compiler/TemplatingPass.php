<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Bundle\FrameworkBundle\DependencyInjection\Compiler;

use Symphony\Bundle\FrameworkBundle\Templating\EngineInterface as FrameworkBundleEngineInterface;
use Symphony\Component\DependencyInjection\Alias;
use Symphony\Component\DependencyInjection\ContainerBuilder;
use Symphony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symphony\Component\DependencyInjection\Reference;
use Symphony\Component\Templating\EngineInterface as ComponentEngineInterface;

class TemplatingPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container)
    {
        if ($container->hasDefinition('templating')) {
            return;
        }

        if ($container->hasAlias('templating')) {
            $container->setAlias(ComponentEngineInterface::class, new Alias('templating', false));
            $container->setAlias(FrameworkBundleEngineInterface::class, new Alias('templating', false));
        }

        if ($container->hasDefinition('templating.engine.php')) {
            $refs = array();
            $helpers = array();
            foreach ($container->findTaggedServiceIds('templating.helper', true) as $id => $attributes) {
                if (isset($attributes[0]['alias'])) {
                    $helpers[$attributes[0]['alias']] = $id;
                    $refs[$id] = new Reference($id);
                }
            }

            if (count($helpers) > 0) {
                $definition = $container->getDefinition('templating.engine.php');
                $definition->addMethodCall('setHelpers', array($helpers));

                if ($container->hasDefinition('templating.engine.php.helpers_locator')) {
                    $container->getDefinition('templating.engine.php.helpers_locator')->replaceArgument(0, $refs);
                }
            }
        }
    }
}
