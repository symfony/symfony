<?php

use Symfony\Component\Config\Definition\Builder\TreeBuilder;

final class FooConfiguration implements \Symfony\Component\Config\Definition\ConfigurationInterface
{
    public function getConfigTreeBuilder()
    {
        $builder = new TreeBuilder();
        $rootNode = $builder->root('foo');
        $rootNode->canBeEnabled();

        return $builder;
    }
}
