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

use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Builder\NodeDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * @author Wouter de Jong <wouter@wouterj.nl>
 *
 * @internal
 */
class CustomAuthenticatorFactory implements AuthenticatorFactoryInterface
{
    public function getPriority(): int
    {
        return 0;
    }

    public function getKey(): string
    {
        return 'custom_authenticators';
    }

    /**
     * @param ArrayNodeDefinition $builder
     */
    public function addConfiguration(NodeDefinition $builder): void
    {
        $builder
            ->info('An array of service ids for all of your "authenticators"')
            ->requiresAtLeastOneElement()
            ->prototype('scalar')->end();

        // get the parent array node builder ("firewalls") from inside the children builder
        $factoryRootNode = $builder->end()->end();
        $factoryRootNode
            ->fixXmlConfig('custom_authenticator')
            ->validate()
                ->ifTrue(fn ($v) => isset($v['custom_authenticators']) && empty($v['custom_authenticators']))
                ->then(function ($v) {
                    unset($v['custom_authenticators']);

                    return $v;
                })
            ->end()
        ;
    }

    public function createAuthenticator(ContainerBuilder $container, string $firewallName, array $config, string $userProviderId): array
    {
        return $config;
    }
}
