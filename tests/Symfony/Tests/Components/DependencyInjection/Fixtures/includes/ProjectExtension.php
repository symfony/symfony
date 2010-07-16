<?php

use Symfony\Components\DependencyInjection\ContainerBuilder;
use Symfony\Components\DependencyInjection\Definition;
use Symfony\Components\DependencyInjection\Extension\Extension;

class ProjectExtension extends Extension
{
    public function barLoad(array $config, ContainerBuilder $configuration)
    {
        $configuration->setDefinition('project.service.bar', new Definition('FooClass'));
        $configuration->setParameter('project.parameter.bar', isset($config['foo']) ? $config['foo'] : 'foobar');

        $configuration->setDefinition('project.service.foo', new Definition('FooClass'));
        $configuration->setParameter('project.parameter.foo', isset($config['foo']) ? $config['foo'] : 'foobar');

        return $configuration;
    }

    public function getXsdValidationBasePath()
    {
        return false;
    }

    public function getNamespace()
    {
        return 'http://www.example.com/schema/project';
    }

    public function getAlias()
    {
        return 'project';
    }
}
