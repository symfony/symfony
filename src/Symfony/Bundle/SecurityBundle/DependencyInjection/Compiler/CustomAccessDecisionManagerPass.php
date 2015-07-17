<?php

namespace Symfony\Bundle\SecurityBundle\DependencyInjection\Compiler;


use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Security\Core\Authorization\AccessDecisionManagerInterface;

class CustomAccessDecisionManagerPass implements CompilerPassInterface
{
    /**
     * @inheritDoc
     */
    public function process(ContainerBuilder $container)
    {
        if ($container->hasParameter('security.access.manager.service')) {
            $serviceName = $container->getParameter('security.access.manager.service');

            if ($container->hasDefinition('security.authorization_checker') && $container->hasDefinition(
                    $serviceName
                )
            ) {
                if ($container->get($serviceName) instanceof AccessDecisionManagerInterface) {
                    $definition = $container->getDefinition('security.authorization_checker');
                    $definition->replaceArgument(2, new Reference($serviceName));
                } else {
                    throw new InvalidConfigurationException();
                }
            }
        }
    }

}