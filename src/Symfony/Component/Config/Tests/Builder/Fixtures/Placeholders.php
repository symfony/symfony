<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

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
