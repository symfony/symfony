<?php

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;

final class FooExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container)
    {
        $config = $this->processConfiguration(new \FooConfiguration(), $configs);

        $container->setParameter('foo_extension_enabled', $config['enabled']);
    }

    public function getAlias()
    {
        return 'foo';
    }
}
