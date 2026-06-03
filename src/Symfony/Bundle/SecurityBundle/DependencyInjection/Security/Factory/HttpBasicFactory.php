<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\SecurityBundle\DependencyInjection\Security\Factory;

use Symfony\Component\Config\Definition\Builder\NodeDefinition;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

/**
 * HttpBasicFactory creates services for HTTP basic authentication.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @internal
 */
class HttpBasicFactory implements AuthenticatorFactoryInterface
{
    public const PRIORITY = -50;

    public function createAuthenticator(ContainerBuilder $container, string $firewallName, array $config, string $userProviderId): string
    {
        $authenticatorId = 'security.authenticator.http_basic.'.$firewallName;
        $container
            ->setDefinition($authenticatorId, new ChildDefinition('security.authenticator.http_basic'))
            ->replaceArgument(0, $config['realm'])
            ->replaceArgument(1, new Reference($userProviderId));

        return $authenticatorId;
    }

    public function getPriority(): int
    {
        return self::PRIORITY;
    }

    public function getKey(): string
    {
        return 'http-basic';
    }

    public function addConfiguration(NodeDefinition $node): void
    {
        $node
            ->children()
                ->scalarNode('provider')->end()
                ->scalarNode('realm')->defaultValue('Secured Area')->end()
            ->end()
        ;
    }
}
