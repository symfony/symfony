<?php

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;

class AcmeExtension implements ExtensionInterface
{
    public function load(array $configs, ContainerBuilder $configuration): void
    {
        $configuration->setParameter('acme.configs', $configs);
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
        return 'http://www.example.com/schema/acme';
    }

    public function getAlias(): string
    {
        return 'acme';
    }
}
