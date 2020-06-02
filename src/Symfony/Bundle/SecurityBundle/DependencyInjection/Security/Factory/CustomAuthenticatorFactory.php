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
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Security\Http\EntryPoint\AuthenticationEntryPointInterface;

/**
 * @author Wouter de Jong <wouter@wouterj.nl>
 *
 * @internal
 * @experimental in Symfony 5.1
 */
class CustomAuthenticatorFactory implements AuthenticatorFactoryInterface, SecurityFactoryInterface, EntryPointFactoryInterface
{
    public function create(ContainerBuilder $container, string $id, array $config, string $userProvider, ?string $defaultEntryPoint)
    {
        throw new \LogicException('Custom authenticators are not supported when "security.enable_authenticator_manager" is not set to true.');
    }

    public function getPosition(): string
    {
        return 'pre_auth';
    }

    public function getKey(): string
    {
        return 'custom_authenticators';
    }

    /**
     * @param ArrayNodeDefinition $builder
     */
    public function addConfiguration(NodeDefinition $builder)
    {
        $builder
            ->fixXmlConfig('service')
            ->beforeNormalization()
                ->ifTrue(function ($v) { return is_string($v) || (is_array($v) && !isset($v['services']) && !isset($v['entry_point'])); })
                ->then(function ($v) {
                    return ['services' => (array) $v];
                })
            ->end()
            ->children()
                ->arrayNode('services')
                    ->info('An array of service ids for all of your "authenticators"')
                    ->requiresAtLeastOneElement()
                    ->prototype('scalar')->end()
                ->end()
                ->scalarNode('entry_point')->defaultNull()->end()
            ->end();

        // get the parent array node builder ("firewalls") from inside the children builder
        $factoryRootNode = $builder->end()->end();
        $factoryRootNode
            ->fixXmlConfig('custom_authenticator')
        ;
    }

    public function createAuthenticator(ContainerBuilder $container, string $firewallName, array $config, string $userProviderId): array
    {
        return $config['services'];
    }

    public function registerEntryPoint(ContainerBuilder $container, string $id, array $config): ?string
    {
        if (isset($config['entry_point'])) {
            return $config['entry_point'];
        }

        $entryPoints = [];
        foreach ($config['services'] as $authenticatorId) {
            if (class_exists($authenticatorId) && is_subclass_of($authenticatorId, AuthenticationEntryPointInterface::class)) {
                $entryPoints[] = $authenticatorId;
            }
        }

        if (!$entryPoints) {
            return null;
        }

        if (1 === \count($entryPoints)) {
            return current($entryPoints);
        }

        throw new InvalidConfigurationException(sprintf('Because you have multiple custom authenticators, you need to set the "custom_authenticators.entry_point" key to one of your authenticators (%s).', implode(', ', $config['services'])));
    }
}
