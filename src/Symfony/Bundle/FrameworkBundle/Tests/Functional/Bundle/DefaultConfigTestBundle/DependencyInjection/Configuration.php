<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Tests\Functional\Bundle\DefaultConfigTestBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('default_config_test');

        $treeBuilder->getRootNode()
            ->children()
                ->scalarNode('foo')->defaultValue('%default_config_test_foo%')->end()
                ->scalarNode('baz')->defaultValue('%env(BAZ)%')->end()
            ->end();

        return $treeBuilder;
    }
}
