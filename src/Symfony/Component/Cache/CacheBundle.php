<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Cache;

use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\Cache\Adapter\AbstractAdapter;
use Symfony\Component\Cache\Adapter\AdapterInterface;
use Symfony\Component\Cache\Adapter\ChainAdapter;
use Symfony\Component\Cache\Adapter\TagAwareAdapter;
use Symfony\Component\Cache\DependencyInjection\CacheCollectorPass;
use Symfony\Component\Cache\DependencyInjection\CachePoolClearerPass;
use Symfony\Component\Cache\DependencyInjection\CachePoolPass;
use Symfony\Component\Cache\DependencyInjection\CachePoolPrunerPass;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\Alias;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\Compiler\PassConfig;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Kernel\AbstractBundle;
use Symfony\Component\DependencyInjection\Kernel\RequiredBundle;
use Symfony\Component\DependencyInjection\Kernel\ServicesBundle;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\DependencyInjection\Parameter;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\NamespacedPoolInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

/**
 * Provides the cache pools and their adapters.
 */
#[RequiredBundle(ServicesBundle::class)]
class CacheBundle extends AbstractBundle
{
    public function getPath(): string
    {
        return $this->path ??= __DIR__;
    }

    public function build(ContainerBuilder $container): void
    {
        $container->addCompilerPass(new CachePoolPass(), PassConfig::TYPE_BEFORE_OPTIMIZATION, 32);
        $container->addCompilerPass(new CachePoolClearerPass(), PassConfig::TYPE_AFTER_REMOVING);
        $container->addCompilerPass(new CachePoolPrunerPass(), PassConfig::TYPE_AFTER_REMOVING);

        if ($container->getParameter('kernel.debug')) {
            $container->addCompilerPass(new CacheCollectorPass(), PassConfig::TYPE_BEFORE_REMOVING);
        }
    }

    public function configure(DefinitionConfigurator $definition): void
    {
        $definition->rootNode()
            // "app" is checked before normalization so that setting it explicitly is told apart from
            // its default value, and after merging so that the two options cannot come from two files
            ->beforeNormalization()
                ->ifArray()
                ->then(static function ($v) {
                    if (isset($v['app'], $v['default_provider'])) {
                        throw new InvalidConfigurationException('The "cache.app" and "cache.default_provider" options cannot be used together, the adapter is deduced from the DSN.');
                    }

                    return $v;
                })
            ->end()
            ->validate()
                ->ifTrue(static fn ($v) => isset($v['default_provider']) && 'cache.adapter.filesystem' !== $v['app'])
                ->thenInvalid('The "cache.app" and "cache.default_provider" options cannot be used together, the adapter is deduced from the DSN.')
            ->end()
            ->children()
                ->scalarNode('prefix_seed')
                    ->info('Used to namespace cache keys when using several apps with the same shared backend.')
                    ->defaultValue('_%kernel.project_dir%.%kernel.container_class%')
                    ->example('my-application-name/%kernel.environment%')
                ->end()
                ->scalarNode('app')
                    ->info('App related cache pools configuration. Cannot be combined with "default_provider".')
                    ->defaultValue('cache.adapter.filesystem')
                ->end()
                ->scalarNode('system')
                    ->info('System related cache pools configuration.')
                    ->defaultValue('cache.adapter.system')
                ->end()
                ->scalarNode('directory')->defaultValue('%kernel.share_dir%/pools/app')->end()
                ->scalarNode('default_provider')
                    ->info('DSN of the backend to use for "cache.app"; the adapter is deduced from it. Replaces "app", which cannot be set alongside it.')
                    ->example('%env(APP_CACHE_DSN)%')
                ->end()
                ->scalarNode('default_psr6_provider')->end()
                ->scalarNode('default_redis_provider')->defaultValue('redis://localhost')->end()
                ->scalarNode('default_valkey_provider')->defaultValue('valkey://localhost')->end()
                ->scalarNode('default_memcached_provider')->defaultValue('memcached://localhost')->end()
                ->scalarNode('default_doctrine_dbal_provider')->defaultValue('database_connection')->end()
                ->scalarNode('default_pdo_provider')->defaultNull()->end()
                ->scalarNode('default_mongodb_provider')->defaultValue('mongodb://localhost/app')->end()
                ->arrayNode('pools', 'pool')
                    ->useAttributeAsKey('name')
                    ->prototype('array')
                        ->validate()
                            ->ifTrue(static fn ($v) => isset($v['provider']) && 1 < \count($v['adapters']))
                            ->thenInvalid('Pool cannot have a "provider" while more than one adapter is defined')
                        ->end()
                        ->children()
                            ->arrayNode('adapters', 'adapter')
                                ->performNoDeepMerging()
                                ->info('One or more adapters to chain for creating the pool, defaults to "cache.app".')
                                ->acceptAndWrap(['string'])
                                ->beforeNormalization()
                                    ->ifArray()
                                    ->then(static function ($values) {
                                        if ([0] === array_keys($values) && \is_array($values[0])) {
                                            return $values[0];
                                        }
                                        $adapters = [];

                                        foreach ($values as $k => $v) {
                                            if (\is_int($k) && \is_string($v)) {
                                                $adapters[] = $v;
                                            } elseif (!\is_array($v)) {
                                                $adapters[$k] = $v;
                                            } elseif (isset($v['provider'])) {
                                                $adapters[$v['provider']] = $v['name'] ?? $v;
                                            } else {
                                                $adapters[] = $v['name'] ?? $v;
                                            }
                                        }

                                        return $adapters;
                                    })
                                ->end()
                                ->prototype('scalar')->end()
                            ->end()
                            ->scalarNode('tags')->defaultNull()->end()
                            ->booleanNode('public')->defaultFalse()->end()
                            ->scalarNode('default_lifetime')
                                ->info('Default lifetime of the pool.')
                                ->example('"300" for 5 minutes expressed in seconds, "PT5M" for five minutes expressed as ISO 8601 time interval, or "5 minutes" as a date expression')
                            ->end()
                            ->scalarNode('provider')
                                ->info('Overwrite the setting from the default provider for this adapter.')
                            ->end()
                            ->scalarNode('early_expiration_message_bus')
                                ->example('"messenger.default_bus" to send early expiration events to the default Messenger bus.')
                            ->end()
                            ->scalarNode('clearer')->end()
                            ->scalarNode('marshaller')
                                ->info('The marshaller service to use for this pool.')
                            ->end()
                        ->end()
                    ->end()
                    ->validate()
                        ->ifTrue(static fn ($v) => isset($v['cache.app']) || isset($v['cache.system']))
                        ->thenInvalid('"cache.app" and "cache.system" are reserved names')
                    ->end()
                ->end()
            ->end()
        ;
    }

    public function loadExtension(array $config, ContainerConfigurator $configurator, ContainerBuilder $container): void
    {
        $configurator->import('Resources/config/cache.php');

        $version = new Parameter('container.build_id');
        $container->getDefinition('cache.adapter.apcu')->replaceArgument(2, $version);
        $container->getDefinition('cache.adapter.system')->replaceArgument(2, $version);
        $container->getDefinition('cache.adapter.filesystem')->replaceArgument(2, $config['directory']);

        if (isset($config['prefix_seed'])) {
            $container->setParameter('cache.prefix.seed', $config['prefix_seed']);
        }
        if ($container->hasParameter('cache.prefix.seed')) {
            // Inline any env vars referenced in the parameter
            $container->setParameter('cache.prefix.seed', $container->resolveEnvPlaceholders($container->getParameter('cache.prefix.seed'), true));
        }
        foreach (['psr6', 'redis', 'valkey', 'memcached', 'doctrine_dbal', 'pdo', 'mongodb'] as $name) {
            if (isset($config[$name = 'default_'.$name.'_provider'])) {
                $container->setAlias('cache.'.$name, new Alias(CachePoolPass::getServiceProvider($container, $config[$name]), false));
            }
        }
        foreach (['app', 'system'] as $name) {
            $config['pools']['cache.'.$name] = [
                // an explicit DSN decides which adapter "cache.app" uses, so none is named here
                'adapters' => 'app' === $name && isset($config['default_provider']) ? [] : [$config[$name]],
                'provider' => 'app' === $name ? $config['default_provider'] ?? null : null,
                'public' => true,
                'tags' => false,
            ];
        }
        $nativeTagAwareAdapters = [['cache.adapter.redis_tag_aware'], ['cache.adapter.valkey_tag_aware'], ['cache.adapter.pdo_tag_aware'], ['cache.adapter.mongodb_tag_aware']];
        foreach ($config['pools'] as $name => $pool) {
            if (null === ($pool['provider'] ??= null)) {
                unset($pool['provider']);
            }
            // no adapter named and a provider given: the DSN decides which adapter to build
            $isDsnPool = !$pool['adapters'] && isset($pool['provider']);
            $pool['adapters'] = $pool['adapters'] ?: ['cache.app'];

            $isNativeTagAware = \in_array($pool['adapters'], $nativeTagAwareAdapters, true);
            foreach ($pool['adapters'] as $provider => $adapter) {
                if (\in_array($config['pools'][$adapter]['adapters'] ?? null, $nativeTagAwareAdapters, true)) {
                    $isNativeTagAware = true;
                } elseif ($config['pools'][$adapter]['tags'] ?? false) {
                    $pool['adapters'][$provider] = $adapter = '.'.$adapter.'.inner';
                }
            }

            if ($isDsnPool) {
                $definition = new Definition(AdapterInterface::class)
                    ->setFactory([AbstractAdapter::class, 'createAdapter'])
                    ->setArguments([
                        new Reference(CachePoolPass::getServiceProvider($container, $pool['provider'])),
                        '',
                        0,
                        new Reference('cache.default_marshaller'),
                    ]);
            } elseif (1 === \count($pool['adapters'])) {
                if (!isset($pool['provider']) && !\is_int($provider)) {
                    $pool['provider'] = $provider;
                }
                $definition = new ChildDefinition($adapter);
            } else {
                $definition = new Definition(ChainAdapter::class, [$pool['adapters'], 0]);
                $pool['reset'] = 'reset';
            }

            if ($isNativeTagAware && 'cache.app' === $name) {
                $container->setAlias('cache.app.taggable', $name);
                $definition->addTag('cache.taggable', ['pool' => $name]);
            } elseif ($isNativeTagAware) {
                $tagAwareId = $name;
                $container->setAlias('.'.$name.'.inner', $name);
                $definition->addTag('cache.taggable', ['pool' => $name]);
            } elseif ($pool['tags']) {
                if (true !== $pool['tags'] && ($config['pools'][$pool['tags']]['tags'] ?? false)) {
                    $pool['tags'] = '.'.$pool['tags'].'.inner';
                }
                $container->register($name, TagAwareAdapter::class)
                    ->addArgument(new Reference('.'.$name.'.inner'))
                    ->addArgument(true !== $pool['tags'] ? new Reference($pool['tags']) : null)
                    ->addMethodCall('setLogger', [new Reference('logger', ContainerInterface::IGNORE_ON_INVALID_REFERENCE)])
                    ->setPublic($pool['public'])
                    ->addTag('cache.taggable', ['pool' => $name])
                    ->addTag('monolog.logger', ['channel' => 'cache']);

                $pool['name'] = $tagAwareId = $name;
                $pool['public'] = false;
                $name = '.'.$name.'.inner';
            } elseif (!\in_array($name, ['cache.app', 'cache.system'], true)) {
                $tagAwareId = '.'.$name.'.taggable';
                $container->register($tagAwareId, TagAwareAdapter::class)
                    ->addArgument(new Reference($name))
                    ->addTag('cache.taggable', ['pool' => $name])
                ;
            }

            if (!\in_array($name, ['cache.app', 'cache.system'], true)) {
                $container->registerAliasForArgument($tagAwareId, TagAwareCacheInterface::class, $pool['name'] ?? $name);
                $container->registerAliasForArgument($name, CacheInterface::class, $pool['name'] ?? $name);
                $container->registerAliasForArgument($name, CacheItemPoolInterface::class, $pool['name'] ?? $name);
                $container->registerAliasForArgument($name, NamespacedPoolInterface::class, $pool['name'] ?? $name);
            }

            $definition->setPublic($pool['public']);
            unset($pool['adapters'], $pool['public'], $pool['tags']);

            $definition->addTag('cache.pool', $pool);
            $container->setDefinition($name, $definition);
        }
    }
}
