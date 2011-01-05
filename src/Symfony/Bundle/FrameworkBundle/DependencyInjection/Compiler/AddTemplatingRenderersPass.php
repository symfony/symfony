<?php

namespace Symfony\Bundle\FrameworkBundle\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;

class AddTemplatingRenderersPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container)
    {
        if (!$container->hasDefinition('templating.engine')) {
            return;
        }

        $renderers = array();
        foreach ($container->findTaggedServiceIds('templating.renderer') as $id => $attributes) {
            if (isset($attributes[0]['alias'])) {
                $renderers[$attributes[0]['alias']] = new Reference($id);
                $container->getDefinition($id)->addMethodCall('setEngine', array(new Reference('templating.engine')));
            }
        }

        $helpers = array();
        foreach ($container->findTaggedServiceIds('templating.helper') as $id => $attributes) {
            if (isset($attributes[0]['alias'])) {
                $helpers[$attributes[0]['alias']] = $id;
            }
        }

        $definition = $container->getDefinition('templating.engine');
        $arguments = $definition->getArguments();
        $arguments[2] = $renderers;
        $definition->setArguments($arguments);

        if (count($helpers) > 0) {
            $definition->addMethodCall('setHelpers', array($helpers));
        }
    }
}