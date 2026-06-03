<?php

namespace Symfony\Bundle\SecurityBundle\Tests\DependencyInjection\Fixtures\UserProviderFactory;

use Symfony\Bundle\SecurityBundle\DependencyInjection\Security\UserProvider\UserProviderFactoryInterface;
use Symfony\Bundle\SecurityBundle\Tests\DependencyInjection\Fixtures\UserProvider\DummyProvider;
use Symfony\Component\Config\Definition\Builder\NodeDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;

class DummyProviderFactory implements UserProviderFactoryInterface
{
    public function create(ContainerBuilder $container, $id, $config): void
    {
        $container->setDefinition($id, new Definition(DummyProvider::class));
    }

    public function getKey(): string
    {
        return 'foo';
    }

    public function addConfiguration(NodeDefinition $node): void
    {
    }
}
