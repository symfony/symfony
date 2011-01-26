<?php

namespace Symfony\Bundle\SecurityBundle\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

class AddAuthenticationProvidersPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container)
    {
        if (!$container->hasDefinition('security.authentication.manager')) {
            return;
        }

        $providers = array();
        foreach ($container->findTaggedServiceIds('security.authentication_provider') as $id => $attributes) {
            $providers[] = new Reference($id);
        }

        $container
            ->getDefinition('security.authentication.manager')
            ->setArguments(array($providers))
        ;
    }
}