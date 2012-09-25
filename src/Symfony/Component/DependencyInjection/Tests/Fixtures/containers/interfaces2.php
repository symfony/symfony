<?php

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;

$container = new ContainerBuilder();

$factoryDefinition = new Definition('BarClassFactory');
$container->setDefinition('barFactory', $factoryDefinition);

$definition = new Definition();
$definition->setFactoryService('barFactory');
$definition->setFactoryMethod('createBarClass');
$container->setDefinition('bar', $definition);

return $container;

class BarClass
{
    public $foo;

    public function setBar($foo)
    {
        $this->foo = $foo;
    }
}

class BarClassFactory
{
    public function createBarClass()
    {
        return new BarClass();
    }
}
