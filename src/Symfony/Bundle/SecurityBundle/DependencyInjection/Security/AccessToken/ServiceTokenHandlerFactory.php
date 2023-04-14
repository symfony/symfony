<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\SecurityBundle\DependencyInjection\Security\AccessToken;

use Symfony\Component\Config\Definition\Builder\NodeBuilder;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Configures a token handler from a service id.
 *
 * @see \Symfony\Bundle\SecurityBundle\Tests\DependencyInjection\Security\Factory\AccessTokenFactoryTest
 *
 * @experimental
 */
class ServiceTokenHandlerFactory implements TokenHandlerFactoryInterface
{
    public function create(ContainerBuilder $container, string $id, array|string $config): void
    {
        $container->setDefinition($id, new ChildDefinition($config));
    }

    public function getKey(): string
    {
        return 'id';
    }

    public function addConfiguration(NodeBuilder $node): void
    {
        $node->scalarNode($this->getKey())->end();
    }
}
