<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Bundle\FrameworkBundle\Tests\Functional\Bundle\TestBundle\DependencyInjection;

use Symphony\Component\Config\Definition\Builder\TreeBuilder;
use Symphony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    private $customConfig;

    public function __construct($customConfig = null)
    {
        $this->customConfig = $customConfig;
    }

    public function getConfigTreeBuilder()
    {
        $treeBuilder = new TreeBuilder();
        $rootNode = $treeBuilder->root('test');

        if ($this->customConfig) {
            $this->customConfig->addConfiguration($rootNode);
        }

        return $treeBuilder;
    }
}
