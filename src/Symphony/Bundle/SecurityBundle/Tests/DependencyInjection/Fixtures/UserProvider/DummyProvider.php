<?php

namespace Symphony\Bundle\SecurityBundle\Tests\DependencyInjection\Fixtures\UserProvider;

use Symphony\Bundle\SecurityBundle\DependencyInjection\Security\UserProvider\UserProviderFactoryInterface;
use Symphony\Component\Config\Definition\Builder\NodeDefinition;
use Symphony\Component\DependencyInjection\ContainerBuilder;

class DummyProvider implements UserProviderFactoryInterface
{
    public function create(ContainerBuilder $container, $id, $config)
    {
    }

    public function getKey()
    {
        return 'foo';
    }

    public function addConfiguration(NodeDefinition $node)
    {
    }
}
