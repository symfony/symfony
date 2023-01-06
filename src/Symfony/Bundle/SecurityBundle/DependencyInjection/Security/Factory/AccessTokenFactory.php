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
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Security\Http\AccessToken\FormEncodedBodyExtractor;
use Symfony\Component\Security\Http\AccessToken\HeaderAccessTokenExtractor;
use Symfony\Component\Security\Http\AccessToken\QueryAccessTokenExtractor;

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
                    ->then(static function (string $v) {
                        return [
                            ['service' => $v]
                        ];
                    })
                ->end()
                ->beforeNormalization()
                    // define array key as service
                    ->always(static function (array $value) {
                        foreach ($value as $k => &$item) {
                            if (!is_int($k) && is_string($item)) { // parameters for default header extractor
                                throw new InvalidConfigurationException(
                                    sprintf('Please define extractor as "service_id" string or ["header|query_string|request_body" => ["%s" => "%s", ...].', $k, $item)
                                );
                            } if (!isset($item['service']) && is_string($k)) {
                                $item['service'] = $k;
                            }
                        }

                        return $value;
                    })
                ->end()
                ->cannotBeEmpty()
                ->defaultValue([
                     ['service' => 'security.access_token_extractor.header'],
                ])
                ->arrayPrototype()
                    ->beforeNormalization()
                        ->ifString()
                        ->then(static function ($v) {
                            return ['service' => (string) $v];
                        })
                    ->end()
                    ->children()
                        // here we define acceptable constructor parameter for all predefined extractors
                        ->scalarNode('service')->isRequired()->end()
                        ->scalarNode('parameter')->end()
                        ->scalarNode('headerParameter')->end()
                        ->scalarNode('tokenType')->end()
                    ->end()
                ->end()
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

    public function createAuthenticator(ContainerBuilder $container, string $firewallName, array $config, ?string $userProviderId): string
    {
        $successHandler = isset($config['success_handler']) ? new Reference($this->createAuthenticationSuccessHandler($container, $firewallName, $config)) : null;
        $failureHandler = isset($config['failure_handler']) ? new Reference($this->createAuthenticationFailureHandler($container, $firewallName, $config)) : null;
        $authenticatorId = sprintf('security.authenticator.access_token.%s', $firewallName);
        $extractorId = $this->createExtractor($container, $firewallName, $config['token_extractors']);

        $container
            ->setDefinition($authenticatorId, new ChildDefinition('security.authenticator.access_token'))
            ->replaceArgument(0, new Reference($config['token_handler']))
            ->replaceArgument(1, new Reference($extractorId))
            ->replaceArgument(2, $userProviderId ? new Reference($userProviderId) : null)
            ->replaceArgument(3, $successHandler)
            ->replaceArgument(4, $failureHandler)
            ->replaceArgument(5, $config['realm'])
        ;

        return $authenticatorId;
    }

    /**
     * @param array<array<string>> $extractors
     */
    private function createExtractor(ContainerBuilder $container, string $firewallName, array $extractors): string
    {
        $arguments = [
            'query_string' => ['parameter'],
            'request_body' => ['parameter'],
            'header' => ['headerParameter', 'tokenType'],
        ];

        /**
         * @psalm-var array<string, class-string> $classes
         */
        $classes = [
            'query_string' => QueryAccessTokenExtractor::class,
            'request_body' => FormEncodedBodyExtractor::class,
            'header' => HeaderAccessTokenExtractor::class,
        ];

        $predefinedExtractors = [
            'query_string' => 'security.access_token_extractor.query_string',
            'request_body' => 'security.access_token_extractor.request_body',
            'header' => 'security.access_token_extractor.header',
        ];

        $extractorIds = [];
        $extractorIndex = -1;
        foreach ($extractors as $key => $config) {
            $service = $config['service'];
            unset($config['service']);

            if (!isset($predefinedExtractors[$service])) {
                // its exists predefined service
                $extractorIds[$key] = $service;

                continue;
            }

            $predefinedExtractor = $predefinedExtractors[$service];

            $availableParameters = $arguments[$service];
            $configuredParameters = array_intersect($availableParameters, array_keys($config));
            if (!$configuredParameters) {
                // without deviating parameters it can also be exists predefined service
                $extractorIds[$key] = $predefinedExtractor;

                continue;
            };

            // create concrete extractor
            $extractorId = sprintf('security.authenticator.access_token.%s_extractor.%s.%d', $service, $firewallName, ++$extractorIndex);
            $definition = new Definition($classes[$service]);
            $container
                ->setDefinition($extractorId, $definition);

            foreach ($availableParameters as $i => $parameterName) {
                $definition->setArgument($i, $config[$parameterName] ?? null);
            }

            $extractorIds[$key] = $extractorId;
        };

        if (1 === \count($extractorIds)) {
            return current($extractorIds);
        }
        $extractorId = sprintf('security.authenticator.access_token.chain_extractor.%s', $firewallName);
        $container
            ->setDefinition($extractorId, new ChildDefinition('security.authenticator.access_token.chain_extractor'))
            ->replaceArgument(0, array_map(function (string $extractorId): Reference {
                return new Reference($extractorId);
            }, $extractorIds))
        ;

        return $extractorId;
    }
}
