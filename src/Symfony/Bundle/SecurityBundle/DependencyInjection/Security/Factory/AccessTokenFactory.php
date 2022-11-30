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
 * AccessTokenFactory creates services for Access Token authentication.
 *
 * @author Florent Morselli <florent.morselli@spomky-labs.com>
 *
 * @internal
 */
final class AccessTokenFactory extends AbstractFactory
{
    private const PRIORITY = -40;

    public function __construct()
    {
        $this->options = [];
        $this->defaultFailureHandlerOptions = [];
        $this->defaultSuccessHandlerOptions = [];
    }

    public function addConfiguration(NodeDefinition $node): void
    {
        parent::addConfiguration($node);

        $builder = $node->children();
        $builder
            ->scalarNode('token_handler')->isRequired()->end()
            ->scalarNode('realm')->defaultNull()->end()
            ->arrayNode('token_extractors')
                ->fixXmlConfig('token_extractors')
                ->beforeNormalization()
                    ->ifString()
                    ->then(static function (string $v): array { return [$v]; })
                ->end()
                ->cannotBeEmpty()
                ->defaultValue([
                    'security.access_token_extractor.header',
                ])
                ->scalarPrototype()->end()
            ->end()
        ;
    }

    public function getPriority(): int
    {
        return self::PRIORITY;
    }

    public function getKey(): string
    {
        return 'access_token';
    }

    public function createAuthenticator(ContainerBuilder $container, string $firewallName, array $config, string $userProviderId): string
    {
        $successHandler = isset($config['success_handler']) ? new Reference($this->createAuthenticationSuccessHandler($container, $firewallName, $config)) : null;
        $failureHandler = isset($config['failure_handler']) ? new Reference($this->createAuthenticationFailureHandler($container, $firewallName, $config)) : null;
        $authenticatorId = sprintf('security.authenticator.access_token.%s', $firewallName);
        $extractorId = $this->createExtractor($container, $firewallName, $config['token_extractors']);

        $container
            ->setDefinition($authenticatorId, new ChildDefinition('security.authenticator.access_token'))
            ->replaceArgument(0, new Reference($config['token_handler']))
            ->replaceArgument(1, new Reference($extractorId))
            ->replaceArgument(2, new Reference($userProviderId))
            ->replaceArgument(3, $successHandler)
            ->replaceArgument(4, $failureHandler)
            ->replaceArgument(5, $config['realm'])
        ;

        return $authenticatorId;
    }

    /**
     * @param array<string> $extractors
     */
    private function createExtractor(ContainerBuilder $container, string $firewallName, array $extractors): string
    {
        $aliases = [
            'query_string' => 'security.access_token_extractor.query_string',
            'request_body' => 'security.access_token_extractor.request_body',
            'header' => 'security.access_token_extractor.header',
        ];
        $extractors = array_map(static function (string $extractor) use ($aliases): string {
            return $aliases[$extractor] ?? $extractor;
        }, $extractors);

        if (1 === \count($extractors)) {
            return current($extractors);
        }
        $extractorId = sprintf('security.authenticator.access_token.chain_extractor.%s', $firewallName);
        $container
            ->setDefinition($extractorId, new ChildDefinition('security.authenticator.access_token.chain_extractor'))
            ->replaceArgument(0, array_map(function (string $extractorId): Reference {return new Reference($extractorId); }, $extractors))
        ;

        return $extractorId;
    }
}
