<?php

namespace Symfony\Bundle\FrameworkBundle\Tests\Functional\Bundle\ExtensionWithoutConfigTestBundle\DependencyInjection;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;

class ExtensionWithoutConfigTestExtension implements ExtensionInterface
{
    public function load(array $configs, ContainerBuilder $container)
    {
    }

    public function getNamespace(): string
    {
        return '';
    }

    public function getXsdValidationBasePath(): string|false
    {
        return false;
    }

    public function getAlias(): string
    {
        return 'extension_without_config_test';
    }
}
