<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\RateLimiter;

use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\Argument\IteratorArgument;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Exception\LogicException;
use Symfony\Component\DependencyInjection\Kernel\AbstractBundle;
use Symfony\Component\DependencyInjection\Kernel\RequiredBundle;
use Symfony\Component\DependencyInjection\Kernel\ServicesBundle;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpKernel\EventListener\RateLimitAttributeListener;
use Symfony\Component\Lock\LockInterface;
use Symfony\Component\RateLimiter\DependencyInjection\DefaultLockFactoryPass;
use Symfony\Component\RateLimiter\Storage\CacheStorage;

/**
 * Provides the rate limiter services.
 */
#[RequiredBundle(ServicesBundle::class)]
class RateLimiterBundle extends AbstractBundle
{
    public function getPath(): string
    {
        return $this->path ??= __DIR__;
    }

    public function build(ContainerBuilder $container): void
    {
        $container->addCompilerPass(new DefaultLockFactoryPass());
    }

    public function configure(DefinitionConfigurator $definition): void
    {
        $definition->rootNode()
            ->canBeDisabled()
            ->beforeNormalization()
                ->ifArray()
                ->then(static function ($v) {
                    if (!isset($v['limiters']) && !isset($v['limiter'])) {
                        $v = ['limiters' => $v];

                        // hoist back the keys the shorthand would otherwise read as limiter names
                        foreach (['enabled', 'builder'] as $key) {
                            if (\array_key_exists($key, $v['limiters'])) {
                                $v[$key] = $v['limiters'][$key];
                                unset($v['limiters'][$key]);
                            }
                        }
                    }

                    return $v;
                })
            ->end()
            ->children()
                ->arrayNode('builder')
                    ->info('Configuration for the RateLimiterBuilder service.')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('lock_factory')
                            ->info('The service ID of the lock factory to use with the RateLimiterBuilder.')
                            ->defaultValue('auto')
                        ->end()
                        ->scalarNode('cache_pool')
                            ->info('The cache pool to use with RateLimiterBuilder.')
                            ->defaultValue('cache.rate_limiter')
                        ->end()
                        ->scalarNode('storage_service')
                            ->info('The service ID of a custom storage implementation, this precedes any configured "cache_pool".')
                            ->defaultNull()
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('limiters', 'limiter')
                    ->useAttributeAsKey('name')
                    ->arrayPrototype()
                        ->children()
                            ->scalarNode('lock_factory')
                                ->info('The service ID of the lock factory used by this limiter (or null to disable locking).')
                                ->defaultValue('auto')
                            ->end()
                            ->scalarNode('cache_pool')
                                ->info('The cache pool to use for storing the current limiter state.')
                                ->defaultValue('cache.rate_limiter')
                            ->end()
                            ->scalarNode('storage_service')
                                ->info('The service ID of a custom storage implementation, this precedes any configured "cache_pool".')
                                ->defaultNull()
                            ->end()
                            ->enumNode('policy')
                                ->info('The algorithm to be used by this limiter.')
                                ->isRequired()
                                ->values(['fixed_window', 'token_bucket', 'sliding_window', 'compound', 'no_limit'])
                            ->end()
                            ->arrayNode('limiters', 'limiter')
                                ->info('The limiters to use when using the "compound" policy.')
                                ->acceptAndWrap(['string'])
                                ->beforeNormalization()
                                    ->ifArray()
                                    ->then(static function (array $v) {
                                        $limiters = [];
                                        foreach ($v as $name => $config) {
                                            if (\is_int($name) && \is_string($config)) {
                                                $limiters[$config] = [];
                                            } else {
                                                $limiters[$name] = $config ?? [];
                                            }
                                        }

                                        return $limiters;
                                    })
                                ->end()
                                ->useAttributeAsKey('name')
                                ->arrayPrototype()
                                    ->children()
                                        ->scalarNode('key')
                                            ->info('The key to pass to this limiter, instead of the one passed to the compound limiter\'s create() method.')
                                            ->defaultNull()
                                        ->end()
                                    ->end()
                                ->end()
                            ->end()
                            ->integerNode('limit')
                                ->info('The maximum allowed hits in a fixed interval or burst.')
                            ->end()
                            ->scalarNode('interval')
                                ->info('Configures the fixed interval if "policy" is set to "fixed_window" or "sliding_window". The value must be a number followed by "second", "minute", "hour", "day", "week" or "month" (or their plural equivalent).')
                            ->end()
                            ->arrayNode('rate')
                                ->info('Configures the fill rate if "policy" is set to "token_bucket".')
                                ->children()
                                    ->scalarNode('interval')
                                        ->info('Configures the rate interval. The value must be a number followed by "second", "minute", "hour", "day", "week" or "month" (or their plural equivalent).')
                                    ->end()
                                    ->integerNode('amount')->info('Amount of tokens to add each interval.')->defaultValue(1)->end()
                                ->end()
                            ->end()
                            ->scalarNode('anchor_at')
                                ->info('Aligns the "fixed_window" policy to a calendar (e.g. "2024-01-05 00:00:00 UTC" combined with `interval: 1 month` resets the counter on the 5th of each month). UTC if not specified.')
                                ->defaultNull()
                            ->end()
                        ->end()
                        ->validate()
                            ->ifTrue(static fn ($v) => !\in_array($v['policy'], ['no_limit', 'compound'], true) && !isset($v['limit']))
                            ->thenInvalid('A limit must be provided when using a policy different than "compound" or "no_limit".')
                        ->end()
                        ->validate()
                            ->ifTrue(static fn ($v) => isset($v['anchor_at']) && 'fixed_window' !== $v['policy'])
                            ->thenInvalid('The "anchor_at" option is only supported with the "fixed_window" policy.')
                        ->end()
                        ->validate()
                            ->ifTrue(static fn ($v) => isset($v['anchor_at']) && isset($v['interval']) && !preg_match('/\b(months?|years?)\b/i', $v['interval']))
                            ->thenInvalid('The "anchor_at" option requires an "interval" of at least one month.')
                        ->end()
                    ->end()
                ->end()
            ->end()
        ;
    }

    public function loadExtension(array $config, ContainerConfigurator $configurator, ContainerBuilder $container): void
    {
        if (!$config['enabled']) {
            return;
        }

        $configurator->import('Resources/config/rate_limiter.php');

        if (!class_exists(RateLimitAttributeListener::class)) {
            $container->removeDefinition('rate_limiter.attribute_listener');
        }

        $limiters = [];
        $compoundLimiters = [];
        $lockFactories = [];

        foreach ($config['limiters'] as $name => $limiterConfig) {
            if ('compound' === $limiterConfig['policy']) {
                $compoundLimiters[$name] = $limiterConfig;

                continue;
            }

            unset($limiterConfig['limiters']);

            $limiters[] = $name;

            // default configuration (when used by other DI extensions)
            $limiterConfig += ['lock_factory' => 'lock.factory', 'cache_pool' => 'cache.rate_limiter'];

            $limiter = $container->setDefinition($limiterId = 'limiter.'.$name, new ChildDefinition('limiter'))
                ->addTag('rate_limiter', ['name' => $name]);

            if ('auto' === $limiterConfig['lock_factory']) {
                $lockFactories[$limiterId] = [2, null];
            } elseif (null !== $limiterConfig['lock_factory']) {
                if (!interface_exists(LockInterface::class)) {
                    throw new LogicException(\sprintf('Rate limiter "%s" requires the Lock component to be installed. Try running "composer require symfony/lock".', $name));
                }

                if ('lock.factory' === $limiterConfig['lock_factory']) {
                    $lockFactories[$limiterId] = [2, \sprintf('Rate limiter "%s"', $name)];
                } else {
                    $limiter->replaceArgument(2, new Reference($limiterConfig['lock_factory']));
                }
            }
            unset($limiterConfig['lock_factory']);

            if (null === $storageId = $limiterConfig['storage_service'] ?? null) {
                $container->register($storageId = 'limiter.storage.'.$name, CacheStorage::class)->addArgument(new Reference($limiterConfig['cache_pool']));
            }

            $limiter->replaceArgument(1, new Reference($storageId));
            unset($limiterConfig['storage_service'], $limiterConfig['cache_pool']);

            $limiterConfig['id'] = $name;
            $limiter->replaceArgument(0, $limiterConfig);

            $container->registerAliasForArgument($limiterId, RateLimiterFactoryInterface::class, $name.'.limiter', $name);
        }

        foreach ($compoundLimiters as $name => $limiterConfig) {
            if (!$limiterConfig['limiters']) {
                throw new LogicException(\sprintf('Compound rate limiter "%s" requires at least one sub-limiter.', $name));
            }

            if ($unknownLimiters = array_diff(array_keys($limiterConfig['limiters']), $limiters)) {
                throw new LogicException(\sprintf('Compound rate limiter "%s" references unknown limiter(s) "%s".', $name, implode('", "', $unknownLimiters)));
            }

            $factories = $keys = [];
            foreach ($limiterConfig['limiters'] as $subName => $subConfig) {
                $factories[$subName] = new Reference('limiter.'.$subName);

                if (null !== $subConfig['key']) {
                    $keys[$subName] = $subConfig['key'];
                }
            }

            $container->register($limiterId = 'limiter.'.$name, CompoundRateLimiterFactory::class)
                ->addTag('rate_limiter', ['name' => $name])
                ->setArguments([new IteratorArgument($factories), $keys])
            ;

            $container->registerAliasForArgument($limiterId, RateLimiterFactoryInterface::class, $name.'.limiter', $name);
        }

        $builderConfig = $config['builder'];
        $builder = $container->getDefinition('limiter_builder');

        if (null === $storageId = $builderConfig['storage_service']) {
            $container->register($storageId = 'limiter_builder.storage', CacheStorage::class)->addArgument(new Reference($builderConfig['cache_pool']));
        }

        $builder->replaceArgument(0, new Reference($storageId));

        if ('auto' === $builderConfig['lock_factory']) {
            if (interface_exists(LockInterface::class)) {
                $lockFactories['limiter_builder'] = [1, null];
            }
        } elseif ($builderConfig['lock_factory']) {
            if (!interface_exists(LockInterface::class)) {
                throw new LogicException('Rate Limiter Builder requires the Lock component to be installed. Try running "composer require symfony/lock".');
            }

            if ('lock.factory' === $builderConfig['lock_factory']) {
                $lockFactories['limiter_builder'] = [1, 'Rate Limiter Builder'];
            } else {
                $builder->replaceArgument(1, new Reference($builderConfig['lock_factory']));
            }
        }

        $container->setAlias(RateLimiterBuilder::class, 'limiter_builder');

        if ($lockFactories) {
            $container->setParameter('.rate_limiter.lock_factories', $lockFactories);
        }
    }
}
