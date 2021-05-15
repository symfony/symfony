<?php

namespace Symfony\Component\Config\Tests\Builder\Fixtures;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Placeholders implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $tb = new TreeBuilder('placeholders');
        $rootNode = $tb->getRootNode();
        $rootNode
            ->children()
                ->booleanNode('enabled')->defaultFalse()->end()
                ->floatNode('favorite_float')->end()
                 ->arrayNode('good_integers')
                    ->integerPrototype()->end()
                ->end()
            ->end()
        ;

        return $tb;
    }
}
