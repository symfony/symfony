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
 * Configures a token handler for an OAuth2 Token Introspection endpoint.
 *
 * @internal
 */
class OAuth2TokenHandlerFactory implements TokenHandlerFactoryInterface
{
    public function create(ContainerBuilder $container, string $id, array|string $config): void
    {
        $container->setDefinition($id, new ChildDefinition('security.access_token_handler.oauth2'));
    }

    public function getKey(): string
    {
        return 'oauth2';
    }

    public function addConfiguration(NodeBuilder $node): void
    {
        $node->scalarNode($this->getKey())->end();
    }
}
