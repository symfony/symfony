<?php

namespace Symfony\Bundle\SecurityBundle\Tests\DependencyInjection\Fixtures\UserProvider;

use Symfony\Bundle\SecurityBundle\DependencyInjection\Security\UserProvider\UserProviderFactoryInterface;
use Symfony\Component\Config\Definition\Builder\NodeDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class DummyProvider implements UserProviderFactoryInterface
{
    public function create(ContainerBuilder $container, $id, $config): void
    {
    }

    public function getKey(): string
    {
        return 'foo';
    }

    public function addConfiguration(NodeDefinition $node): void
    {
    }
}
