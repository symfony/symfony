<?php

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;

class ProjectExtension implements ExtensionInterface
{
    public function load(array $configs, ContainerBuilder $configuration)
    {
        $configuration->setParameter('project.configs', $configs);
        $configs = array_filter($configs);

        if ($configs) {
            $config = array_merge(...$configs);
        } else {
            $config = [];
        }

        $configuration->register('project.service.bar', 'FooClass')->setPublic(true);
        $configuration->setParameter('project.parameter.bar', isset($config['foo']) ? $config['foo'] : 'foobar');

        $configuration->register('project.service.foo', 'FooClass')->setPublic(true);
        $configuration->setParameter('project.parameter.foo', isset($config['foo']) ? $config['foo'] : 'foobar');

        return $configuration;
    }

    public function getXsdValidationBasePath()
    {
        return false;
    }

    public function getNamespace(): string
    {
        return 'http://www.example.com/schema/project';
    }

    public function getAlias(): string
    {
        return 'project';
    }

    public function getConfiguration(array $config, ContainerBuilder $container)
    {
    }
}
