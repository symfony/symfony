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

class NodeWithCommentEnd implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $tb = new TreeBuilder('node_with_comment_end');
        $rootNode = $tb->getRootNode();
        $rootNode
            ->children()
                ->scalarNode('schedule')
                    ->info('Cron expression for when syncs should run, e.g. */30 * * * *')
                    ->example('*/30 * * * *')
                    ->defaultValue('*/30 * * * *')
                ->end()
            ->end()
        ;

        return $tb;
    }
}
