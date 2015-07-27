<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\SecurityBundle\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

class CustomAccessDecisionManagerPass implements CompilerPassInterface
{
    /**
     * {@inheritdoc}
     */
    public function process(ContainerBuilder $container)
    {
        $serviceName = $container->getParameter('security.access.manager.service');

        if ($container->hasDefinition('security.authorization_checker') && $container->hasDefinition($serviceName)) {
            // We must assume that the class value has been correctly filled, even if the service is created by a factory
            $class = $container->getParameterBag()->resolveValue($container->getDefinition($serviceName)->getClass());
            $refClass = new \ReflectionClass($class);

            if ($refClass->implementsInterface('Symfony\Component\Security\Core\Authorization\AccessDecisionManagerInterface')) {
                $definition = $container->getDefinition('security.authorization_checker');
                $definition->replaceArgument(2, new Reference($serviceName));
            } else {
                throw new \InvalidArgumentException(sprintf('Service "%s" must implement interface "AccessDecisionManagerInterface".', $serviceName));
            }
        }
    }
}
