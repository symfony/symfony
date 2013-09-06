<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Doctrine\DependencyInjection\Security\UserProvider;

use Symfony\Component\Config\Definition\Builder\NodeDefinition;

use Symfony\Bundle\SecurityBundle\DependencyInjection\Security\UserProvider\UserProviderFactoryInterface;
use Symfony\Component\DependencyInjection\DefinitionDecorator;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * EntityFactory creates services for Doctrine user provider.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Christophe Coevoet <stof@notk.org>
 */
class EntityFactory implements UserProviderFactoryInterface
{
    private $key;
    private $providerId;

    public function __construct($key, $providerId)
    {
        $this->key = $key;
        $this->providerId = $providerId;
    }

    public function create(ContainerBuilder $container, $id, $config)
    {
        $container
            ->setDefinition($id, new DefinitionDecorator($this->providerId))
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
