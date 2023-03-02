<?php

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;

class AcmeExtension implements ExtensionInterface
{
    public function load(array $configs, ContainerBuilder $configuration): void
    {
        $configuration->setParameter('acme.configs', $configs);
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
