<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Bundle\TwigBundle\DependencyInjection\Compiler;

use Symphony\Component\DependencyInjection\Compiler\PriorityTaggedServiceTrait;
use Symphony\Component\DependencyInjection\ContainerBuilder;
use Symphony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symphony\Component\DependencyInjection\Reference;

/**
 * Adds tagged twig.extension services to twig service.
 *
 * @author Fabien Potencier <fabien@symphony.com>
 */
class TwigEnvironmentPass implements CompilerPassInterface
{
    use PriorityTaggedServiceTrait;

    public function process(ContainerBuilder $container)
    {
        if (false === $container->hasDefinition('twig')) {
            return;
        }

        $definition = $container->getDefinition('twig');

        // Extensions must always be registered before everything else.
        // For instance, global variable definitions must be registered
        // afterward. If not, the globals from the extensions will never
        // be registered.
        $currentMethodCalls = $definition->getMethodCalls();
        $twigBridgeExtensionsMethodCalls = array();
        $othersExtensionsMethodCalls = array();
        foreach ($this->findAndSortTaggedServices('twig.extension', $container) as $extension) {
            $methodCall = array('addExtension', array($extension));
            $extensionClass = $container->getDefinition((string) $extension)->getClass();

            if (is_string($extensionClass) && 0 === strpos($extensionClass, 'Symphony\Bridge\Twig\Extension')) {
                $twigBridgeExtensionsMethodCalls[] = $methodCall;
            } else {
                $othersExtensionsMethodCalls[] = $methodCall;
            }
        }

        if (!empty($twigBridgeExtensionsMethodCalls) || !empty($othersExtensionsMethodCalls)) {
            $definition->setMethodCalls(array_merge($twigBridgeExtensionsMethodCalls, $othersExtensionsMethodCalls, $currentMethodCalls));
        }
    }
}
