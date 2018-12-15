<?php

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;

class ProjectExtension implements ExtensionInterface
{
    public function load(array $configs, ContainerBuilder $configuration)
    {
        $configuration->setParameter('project.configs', $configs);
        $configs = array_filter($configs);

        if ($configs) {
            $config = call_user_func_array('array_merge', $configs);
        } else {
            $config = [];
        }

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

    public function getConfiguration(array $config, ContainerBuilder $container)
    {
    }
}
