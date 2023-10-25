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

use Symfony\Bundle\SecurityBundle\DependencyInjection\Security\UserProvider\UserProviderFactoryInterface;
use Symfony\Component\Config\Definition\Builder\NodeDefinition;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * EntityFactory creates services for Doctrine user provider.
 *
 * @final since Symfony 6.4
 *
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Christophe Coevoet <stof@notk.org>
 */
class EntityFactory implements UserProviderFactoryInterface
{
    public function __construct(
        private readonly string $key,
        private readonly string $providerId,
    ) {
    }

    public function create(ContainerBuilder $container, string $id, array $config): void
    {
        $container
            ->setDefinition($id, new ChildDefinition($this->providerId))
            ->addArgument($config['class'])
            ->addArgument($config['property'])
            ->addArgument($config['manager_name'])
        ;
    }

    public function getKey(): string
    {
        return $this->key;
    }

    public function addConfiguration(NodeDefinition $node): void
    {
        $node
            ->children()
                ->scalarNode('class')
                    ->isRequired()
                    ->info('The full entity class name of your user class.')
                    ->cannotBeEmpty()
                ->end()
                ->scalarNode('property')->defaultNull()->end()
                ->scalarNode('manager_name')->defaultNull()->end()
            ->end()
        ;
    }
}
