<?php

namespace Symfony\Bundle\SecurityBundle\Tests\DependencyInjection\Fixtures\UserProviderFactory;

use Symfony\Bundle\SecurityBundle\DependencyInjection\Security\UserProvider\UserProviderFactoryInterface;
use Symfony\Bundle\SecurityBundle\Tests\DependencyInjection\Fixtures\UserProvider\DummyProvider;
use Symfony\Component\Config\Definition\Builder\NodeDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;

class CustomProviderFactory implements UserProviderFactoryInterface
{
    public function create(ContainerBuilder $container, string $id, array $config): void
    {
        $definition = $container->setDefinition($id, new Definition(DummyProvider::class));
        $definition->setArgument('$foo', $config['foo']);
    }

    public function getKey(): string
    {
        return 'custom';
    }

    public function addConfiguration(NodeDefinition $builder): void
    {
        $builder
            ->children()
                ->scalarNode('foo')
                    ->defaultValue('bar')
                ->end()
            ->end()
        ;
    }
}
