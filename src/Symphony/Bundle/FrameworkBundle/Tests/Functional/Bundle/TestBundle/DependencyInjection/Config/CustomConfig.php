<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Bundle\FrameworkBundle\Tests\Functional\Bundle\TestBundle\DependencyInjection\Config;

class CustomConfig
{
    public function addConfiguration($rootNode)
    {
        $rootNode
            ->children()
                ->scalarNode('custom')->end()
                ->arrayNode('array')
                    ->children()
                        ->scalarNode('child1')->end()
                        ->scalarNode('child2')->end()
                        ->end()
                    ->end()
                ->end()
            ->end()
        ;
    }
}
