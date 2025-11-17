<?php

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;

class AcmeExtension implements ExtensionInterface
{
    public function load(array $configs, ContainerBuilder $configuration): void
    {
        $configuration->setParameter('acme.configs', $configs);
    }

    public function getAlias(): string
    {
        return 'acme';
    }
}
