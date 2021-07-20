<?php

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;

class AcmeExtension implements ExtensionInterface
{
    public function load(array $configs, ContainerBuilder $configuration)
    {
        $configuration->setParameter('acme.configs', $configs);

        return $configuration;
    }

    public function getXsdValidationBasePath(): string|false
    {
        return false;
    }

    public function getNamespace(): string
    {
        return 'http://www.example.com/schema/acme';
    }

    public function getAlias(): string
    {
        return 'acme';
    }
}
