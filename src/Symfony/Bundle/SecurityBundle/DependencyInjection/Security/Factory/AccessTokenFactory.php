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

use Symfony\Bundle\SecurityBundle\DependencyInjection\Security\AccessToken\TokenHandlerFactoryInterface;
use Symfony\Component\Config\Definition\Builder\NodeDefinition;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
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
final class AccessTokenFactory extends AbstractFactory implements StatelessAuthenticatorFactoryInterface
{
    private const PRIORITY = -40;

    /**
     * @param array<TokenHandlerFactoryInterface> $tokenHandlerFactories
     */
    public function __construct(private readonly array $tokenHandlerFactories)
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
            ->scalarNode('realm')->defaultNull()->end()
            ->arrayNode('token_extractors')
                ->fixXmlConfig('token_extractors')
                ->beforeNormalization()
                    ->ifString()
                    ->then(static fn (string $v): array => [$v])
                ->end()
                ->cannotBeEmpty()
                ->defaultValue([
                    'security.access_token_extractor.header',
                ])
                ->scalarPrototype()->end()
            ->end()
        ;

        $tokenHandlerNodeBuilder = $builder
            ->arrayNode('token_handler')
                ->example([
                    'id' => 'App\Security\CustomTokenHandler',
                ])

                ->beforeNormalization()
                    ->ifString()
                    ->then(static function (string $v): array { return ['id' => $v]; })
                ->end()

                ->beforeNormalization()
                    ->ifTrue(static function ($v) { return \is_array($v) && 1 < \count($v); })
                    ->then(static function () { throw new InvalidConfigurationException('You cannot configure multiple token handlers.'); })
                ->end()

                // "isRequired" must be set otherwise the following custom validation is not called
                ->isRequired()
                ->beforeNormalization()
                    ->ifTrue(static function ($v) { return \is_array($v) && !$v; })
                    ->then(static function () { throw new InvalidConfigurationException('You must set a token handler.'); })
                ->end()

                ->children()
        ;

        foreach ($this->tokenHandlerFactories as $factory) {
            $factory->addConfiguration($tokenHandlerNodeBuilder);
        }

        $tokenHandlerNodeBuilder->end();
    }

    public function getPriority(): int
    {
        return self::PRIORITY;
    }

    public function getKey(): string
    {
        return 'access_token';
    }

    public function createAuthenticator(ContainerBuilder $container, string $firewallName, array $config, ?string $userProviderId): string
    {
        $successHandler = isset($config['success_handler']) ? new Reference($this->createAuthenticationSuccessHandler($container, $firewallName, $config)) : null;
        $failureHandler = isset($config['failure_handler']) ? new Reference($this->createAuthenticationFailureHandler($container, $firewallName, $config)) : null;
        $authenticatorId = sprintf('security.authenticator.access_token.%s', $firewallName);
        $extractorId = $this->createExtractor($container, $firewallName, $config['token_extractors']);
        $tokenHandlerId = $this->createTokenHandler($container, $firewallName, $config['token_handler'], $userProviderId);

        $container
            ->setDefinition($authenticatorId, new ChildDefinition('security.authenticator.access_token'))
            ->replaceArgument(0, new Reference($tokenHandlerId))
            ->replaceArgument(1, new Reference($extractorId))
            ->replaceArgument(2, $userProviderId ? new Reference($userProviderId) : null)
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
        $extractors = array_map(static fn (string $extractor): string => $aliases[$extractor] ?? $extractor, $extractors);

        if (1 === \count($extractors)) {
            return current($extractors);
        }
        $extractorId = sprintf('security.authenticator.access_token.chain_extractor.%s', $firewallName);
        $container
            ->setDefinition($extractorId, new ChildDefinition('security.authenticator.access_token.chain_extractor'))
            ->replaceArgument(0, array_map(fn (string $extractorId): Reference => new Reference($extractorId), $extractors))
        ;

        return $extractorId;
    }

    private function createTokenHandler(ContainerBuilder $container, string $firewallName, array $config, ?string $userProviderId): string
    {
        $key = array_keys($config)[0];
        $id = sprintf('security.access_token_handler.%s', $firewallName);

        foreach ($this->tokenHandlerFactories as $factory) {
            if ($key !== $factory->getKey()) {
                continue;
            }

            $factory->create($container, $id, $config[$key], $userProviderId);
        }

        return $id;
    }
}
