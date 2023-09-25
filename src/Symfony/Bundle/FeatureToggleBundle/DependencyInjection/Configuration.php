<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FeatureToggleBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

final class Configuration implements ConfigurationInterface
{
    private const KNOWN_STRATEGY_TYPES = ['grant', 'deny', 'not', 'date', 'env', 'request_header', 'request_query', 'request_attribute', 'priority', 'affirmative', 'unanimous'];

    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('feature_toggle');
        $treeBuilder->getRootNode() // @phpstan-ignore-line
            ->children()
                // strategies
                ->arrayNode('strategies')
                    ->useAttributeAsKey('name', false)
                    ->arrayPrototype()
                        ->children()
                            ->scalarNode('name')
                                ->isRequired()
                                ->cannotBeEmpty()
                                ->info('Will become the service ID in the container.')
                                ->example('header.feature-strategy')
                            ->end()
                            ->scalarNode('type')
                                ->isRequired()
                                ->cannotBeEmpty()
                                ->info(sprintf('Can be one of : %s. Or a service ID.', implode(', ', self::KNOWN_STRATEGY_TYPES)))
                                ->example('request_header')
                            ->end()
                            ->variableNode('with')
                                ->defaultValue([])
                                ->example(['name' => 'Some-Header'])
                                ->info('Additional information required. Depends on type.')
                            ->end()
                        ->end()
                        ->beforeNormalization()
                            ->always()
                            ->then(static function (array $strategy): array {
                                $defaultWith = match ($strategy['type']) {
                                    'date' => ['since' => null, 'until' => null, 'includeSince' => false, 'includeUntil' => false],
                                    'not' => ['strategy' => null],
                                    'env', 'request_header', 'request_query', 'request_attribute' => ['name' => null],
                                    'priority', 'affirmative', 'unanimous' => ['strategies' => null],
                                    default => [],
                                };

                                $strategy['with'] ??= [];
                                $strategy['with'] += $defaultWith;

                                return $strategy;
                            })
                        ->end()
                        ->validate()
                            ->always()
                            ->then(static function (array $strategy): array {
                                $validator = match ($strategy['type']) {
                                    'date' => static function (array $with): void {
                                        if ('' === trim((string) $with['since'].(string) $with['until'])) {
                                            throw new \InvalidArgumentException('Either "since" or "until" must be provided.');
                                        }
                                    },
                                    'not' => static function (array $with): void {
                                        if ('' === (string) $with['strategy']) {
                                            throw new \InvalidArgumentException('"strategy" must be provided.');
                                        }
                                    },
                                    'env' => static function (array $with): void {
                                        if ('' === (string) $with['name']) {
                                            throw new \InvalidArgumentException('"name" must be provided.');
                                        }
                                    },
                                    'request_header' => static function (array $with): void {
                                        if ('' === (string) $with['name']) {
                                            throw new \InvalidArgumentException('"name" must be provided.');
                                        }
                                    },
                                    'request_query' => static function (array $with): void {
                                        if ('' === (string) $with['name']) {
                                            throw new \InvalidArgumentException('"name" must be provided.');
                                        }
                                    },
                                    'request_attribute' => static function (array $with): void {
                                        if ('' === (string) $with['name']) {
                                            throw new \InvalidArgumentException('"name" must be provided.');
                                        }
                                    },
                                    'priority', 'affirmative', 'unanimous' => static function (array $with): void {
                                        if ([] === (array) $with['strategies']) {
                                            throw new \InvalidArgumentException('"strategies" must be provided.');
                                        }
                                    },
                                    default => static fn (): bool => true,
                                };

                                $validator($strategy['with']);

                                return $strategy;
                            })
                        ->end()
                    ->end()
                ->end()
                // features
                ->arrayNode('features')
                    ->useAttributeAsKey('name', false)
                    ->arrayPrototype()
                        ->children()
                            ->scalarNode('name')
                                ->isRequired()
                                ->cannotBeEmpty()
                                ->info('Name to be used for checking.')
                                ->example('my-feature')
                            ->end()
                            ->scalarNode('description')->defaultValue('')->end()
                            ->booleanNode('default')
                                ->defaultFalse()
                                ->treatNullLike(false)
                                ->isRequired()
                                ->info('Will be used as a fallback mechanism if the strategy return StrategyResult::Abstain.')
                            ->end()
                            ->scalarNode('strategy')
                                ->isRequired()
                                ->cannotBeEmpty()
                                ->example('header.feature-strategy')
                                ->info('Strategy to be used for this feature. Can be one of "feature_toggle.strategies[].name" or a valid service id that implements StrategyInterface::class.')
                            ->end()
                        ->end()
                    ->end()
                ->end()
            ->end()
        ;

        return $treeBuilder;
    }
}
