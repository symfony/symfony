<?php

namespace Symfony\Bundle\FrameworkBundle\Tests\Functional\Bundle\DebugAutoconfigurationBundle\DependencyInjection;

use Symfony\Bundle\FrameworkBundle\Tests\Functional\Bundle\DebugAutoconfigurationBundle\Autoconfiguration\Bindings;
use Symfony\Bundle\FrameworkBundle\Tests\Functional\Bundle\DebugAutoconfigurationBundle\Autoconfiguration\MethodCalls;
use Symfony\Bundle\FrameworkBundle\Tests\Functional\Bundle\DebugAutoconfigurationBundle\Autoconfiguration\TagsAttributes;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Reference;

class DebugAutoconfigurationExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container)
    {
        $container->registerForAutoconfiguration(MethodCalls::class)
            ->addMethodCall('setMethodOne', array(new Reference('logger')))
            ->addMethodCall('setMethodTwo', array(['paramOne', 'paramOne']));

        $container->registerForAutoconfiguration(Bindings::class)
            ->setBindings(array(
                '$paramOne' => new Reference('logger'),
                '$paramTwo' => 'binding test',
            ));

        $container->registerForAutoconfiguration(TagsAttributes::class)
            ->addTag('debugautoconfiguration.tag1', array('method' => 'debug'))
            ->addTag('debugautoconfiguration.tag2', array('test'))
        ;
    }
}
