<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Bridge\Doctrine\DependencyInjection\Security\UserProvider;

use Symphony\Component\Config\Definition\Builder\NodeDefinition;
use Symphony\Bundle\SecurityBundle\DependencyInjection\Security\UserProvider\UserProviderFactoryInterface;
use Symphony\Component\DependencyInjection\ChildDefinition;
use Symphony\Component\DependencyInjection\ContainerBuilder;

/**
 * EntityFactory creates services for Doctrine user provider.
 *
 * @author Fabien Potencier <fabien@symphony.com>
 * @author Christophe Coevoet <stof@notk.org>
 */
class EntityFactory implements UserProviderFactoryInterface
{
    private $key;
    private $providerId;

    public function __construct(string $key, string $providerId)
    {
        $this->key = $key;
        $this->providerId = $providerId;
    }

    public function create(ContainerBuilder $container, $id, $config)
    {
        $container
            ->setDefinition($id, new ChildDefinition($this->providerId))
            ->addArgument($config['class'])
            ->addArgument($config['property'])
            ->addArgument($config['manager_name'])
        ;
    }

    public function getKey()
    {
        return $this->key;
    }

    public function addConfiguration(NodeDefinition $node)
    {
        $node
            ->children()
                ->scalarNode('class')->isRequired()->cannotBeEmpty()->end()
                ->scalarNode('property')->defaultNull()->end()
                ->scalarNode('manager_name')->defaultNull()->end()
            ->end()
        ;
    }
}
