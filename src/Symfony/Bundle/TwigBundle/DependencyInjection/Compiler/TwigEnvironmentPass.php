<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\TwigBundle\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\Compiler\PriorityTaggedServiceTrait;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Adds tagged twig.extension services to twig service.
 *
 * @author Fabien Potencier <fabien@symfony.com>
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

            if (\is_string($extensionClass) && 0 === strpos($extensionClass, 'Symfony\Bridge\Twig\Extension')) {
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
