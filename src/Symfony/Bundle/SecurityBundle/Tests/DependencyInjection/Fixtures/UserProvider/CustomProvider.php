<?php

namespace Symfony\Bundle\SecurityBundle\Tests\DependencyInjection\Fixtures\UserProvider;

use Symfony\Bundle\SecurityBundle\DependencyInjection\Security\UserProvider\UserProviderFactoryInterface;
use Symfony\Component\Config\Definition\Builder\NodeDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class CustomProvider implements UserProviderFactoryInterface
{
    public function create(ContainerBuilder $container, string $id, array $config)
    {
    }

    public function getKey(): string
    {
        return 'custom';
    }

    public function addConfiguration(NodeDefinition $builder)
    {
        $builder
            ->children()
                ->scalarNode('foo')->defaultValue('bar')->end()
            ->end()
        ;
    }
}
