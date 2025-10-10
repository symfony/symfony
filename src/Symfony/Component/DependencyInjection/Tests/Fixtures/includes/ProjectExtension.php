<?php

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;

class ProjectExtension implements ExtensionInterface
{
    public function load(array $configs, ContainerBuilder $configuration): void
    {
        $configuration->setParameter('project.configs', $configs);
        $configs = array_filter($configs);

        if ($configs) {
            $config = array_merge(...$configs);
        } else {
            $config = [];
        }

        $configuration->register('project.service.bar', 'FooClass')->setPublic(true);
        $configuration->setParameter('project.parameter.bar', $config['foo'] ?? 'foobar');

        $configuration->register('project.service.foo', 'FooClass')->setPublic(true);
        $configuration->setParameter('project.parameter.foo', $config['foo'] ?? 'foobar');
    }

    /**
     * @deprecated since Symfony 7.4, to be removed in Symfony 8.0 together with XML support.
     */
    public function getXsdValidationBasePath(): string|false
    {
        return false;
    }

    /**
     * @deprecated since Symfony 7.4, to be removed in Symfony 8.0 together with XML support.
     */
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
