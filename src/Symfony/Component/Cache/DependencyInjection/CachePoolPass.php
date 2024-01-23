<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Cache\DependencyInjection;

use Symfony\Component\Cache\Adapter\AbstractAdapter;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\Adapter\ChainAdapter;
use Symfony\Component\Cache\Adapter\NullAdapter;
use Symfony\Component\Cache\Adapter\ParameterNormalizer;
use Symfony\Component\Cache\Messenger\EarlyExpirationDispatcher;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;
use Symfony\Component\DependencyInjection\Reference;

/**
 * @author Nicolas Grekas <p@tchwork.com>
 */
class CachePoolPass implements CompilerPassInterface
{
    private $cachePoolTag;
    private $kernelResetTag;
    private $cacheClearerId;
    private $cachePoolClearerTag;
    private $cacheSystemClearerId;
    private $cacheSystemClearerTag;
    private $reverseContainerId;
    private $reversibleTag;
    private $messageHandlerId;

    public function __construct(string $cachePoolTag = 'cache.pool', string $kernelResetTag = 'kernel.reset', string $cacheClearerId = 'cache.global_clearer', string $cachePoolClearerTag = 'cache.pool.clearer', string $cacheSystemClearerId = 'cache.system_clearer', string $cacheSystemClearerTag = 'kernel.cache_clearer', string $reverseContainerId = 'reverse_container', string $reversibleTag = 'container.reversible', string $messageHandlerId = 'cache.early_expiration_handler')
    {
        if (0 < \func_num_args()) {
            trigger_deprecation('symfony/cache', '5.3', 'Configuring "%s" is deprecated.', __CLASS__);
        }

        $this->cachePoolTag = $cachePoolTag;
        $this->kernelResetTag = $kernelResetTag;
        $this->cacheClearerId = $cacheClearerId;
        $this->cachePoolClearerTag = $cachePoolClearerTag;
        $this->cacheSystemClearerId = $cacheSystemClearerId;
        $this->cacheSystemClearerTag = $cacheSystemClearerTag;
        $this->reverseContainerId = $reverseContainerId;
        $this->reversibleTag = $reversibleTag;
        $this->messageHandlerId = $messageHandlerId;
    }

    /**
     * {@inheritdoc}
     */
    public function process(ContainerBuilder $container)
    {
        if ($container->hasParameter('cache.prefix.seed')) {
            $seed = $container->getParameterBag()->resolveValue($container->getParameter('cache.prefix.seed'));
        } else {
            $seed = '_'.$container->getParameter('kernel.project_dir');
            $seed .= '.'.$container->getParameter('kernel.container_class');
        }

        $needsMessageHandler = false;
        $allPools = [];
        $clearers = [];
        $attributes = [
            'provider',
            'name',
            'namespace',
            'default_lifetime',
            'early_expiration_message_bus',
            'reset',
        ];
        foreach ($container->findTaggedServiceIds($this->cachePoolTag) as $id => $tags) {
            $adapter = $pool = $container->getDefinition($id);
            if ($pool->isAbstract()) {
                continue;
            }
            $class = $adapter->getClass();
            while ($adapter instanceof ChildDefinition) {
                $adapter = $container->findDefinition($adapter->getParent());
                $class = $class ?: $adapter->getClass();
                if ($t = $adapter->getTag($this->cachePoolTag)) {
                    $tags[0] += $t[0];
                }
            }
            $name = $tags[0]['name'] ?? $id;
            if (!isset($tags[0]['namespace'])) {
                $namespaceSeed = $seed;
                if (null !== $class) {
                    $namespaceSeed .= '.'.$class;
                }

                $tags[0]['namespace'] = $this->getNamespace($namespaceSeed, $name);
            }
            if (isset($tags[0]['clearer'])) {
                $clearer = $tags[0]['clearer'];
                while ($container->hasAlias($clearer)) {
                    $clearer = (string) $container->getAlias($clearer);
                }
            } else {
                $clearer = null;
            }
            unset($tags[0]['clearer'], $tags[0]['name']);

            if (isset($tags[0]['provider'])) {
                $tags[0]['provider'] = new Reference(static::getServiceProvider($container, $tags[0]['provider']));
            }

            if (ChainAdapter::class === $class) {
                $adapters = [];
                foreach ($adapter->getArgument(0) as $provider => $adapter) {
                    if ($adapter instanceof ChildDefinition) {
                        $chainedPool = $adapter;
                    } else {
                        $chainedPool = $adapter = new ChildDefinition($adapter);
                    }

                    $chainedTags = [\is_int($provider) ? [] : ['provider' => $provider]];
                    $chainedClass = '';

                    while ($adapter instanceof ChildDefinition) {
                        $adapter = $container->findDefinition($adapter->getParent());
                        $chainedClass = $chainedClass ?: $adapter->getClass();
                        if ($t = $adapter->getTag($this->cachePoolTag)) {
                            $chainedTags[0] += $t[0];
                        }
                    }

                    if (ChainAdapter::class === $chainedClass) {
                        throw new InvalidArgumentException(sprintf('Invalid service "%s": chain of adapters cannot reference another chain, found "%s".', $id, $chainedPool->getParent()));
                    }

                    $i = 0;

                    if (isset($chainedTags[0]['provider'])) {
                        $chainedPool->replaceArgument($i++, new Reference(static::getServiceProvider($container, $chainedTags[0]['provider'])));
                    }

                    if (isset($tags[0]['namespace']) && !\in_array($adapter->getClass(), [ArrayAdapter::class, NullAdapter::class], true)) {
                        $chainedPool->replaceArgument($i++, $tags[0]['namespace']);
                    }

                    if (isset($tags[0]['default_lifetime'])) {
                        $chainedPool->replaceArgument($i++, $tags[0]['default_lifetime']);
                    }

                    $adapters[] = $chainedPool;
                }

                $pool->replaceArgument(0, $adapters);
                unset($tags[0]['provider'], $tags[0]['namespace']);
                $i = 1;
            } else {
                $i = 0;
            }

            foreach ($attributes as $attr) {
                if (!isset($tags[0][$attr])) {
                    // no-op
                } elseif ('reset' === $attr) {
                    if ($tags[0][$attr]) {
                        $pool->addTag($this->kernelResetTag, ['method' => $tags[0][$attr]]);
                    }
                } elseif ('early_expiration_message_bus' === $attr) {
                    $needsMessageHandler = true;
                    $pool->addMethodCall('setCallbackWrapper', [(new Definition(EarlyExpirationDispatcher::class))
                        ->addArgument(new Reference($tags[0]['early_expiration_message_bus']))
                        ->addArgument(new Reference($this->reverseContainerId))
                        ->addArgument((new Definition('callable'))
                            ->setFactory([new Reference($id), 'setCallbackWrapper'])
                            ->addArgument(null)
                        ),
                    ]);
                    $pool->addTag($this->reversibleTag);
                } elseif ('namespace' !== $attr || !\in_array($class, [ArrayAdapter::class, NullAdapter::class], true)) {
                    $argument = $tags[0][$attr];

                    if ('default_lifetime' === $attr && !is_numeric($argument)) {
                        $argument = (new Definition('int', [$argument]))
                            ->setFactory([ParameterNormalizer::class, 'normalizeDuration']);
                    }

                    $pool->replaceArgument($i++, $argument);
                }
                unset($tags[0][$attr]);
            }
            if (!empty($tags[0])) {
                throw new InvalidArgumentException(sprintf('Invalid "%s" tag for service "%s": accepted attributes are "clearer", "provider", "name", "namespace", "default_lifetime", "early_expiration_message_bus" and "reset", found "%s".', $this->cachePoolTag, $id, implode('", "', array_keys($tags[0]))));
            }

            if (null !== $clearer) {
                $clearers[$clearer][$name] = new Reference($id, $container::IGNORE_ON_UNINITIALIZED_REFERENCE);
            }

            $allPools[$name] = new Reference($id, $container::IGNORE_ON_UNINITIALIZED_REFERENCE);
        }

        if (!$needsMessageHandler) {
            $container->removeDefinition($this->messageHandlerId);
        }

        $notAliasedCacheClearerId = $this->cacheClearerId;
        while ($container->hasAlias($notAliasedCacheClearerId)) {
            $notAliasedCacheClearerId = (string) $container->getAlias($notAliasedCacheClearerId);
        }
        if ($container->hasDefinition($notAliasedCacheClearerId)) {
            $clearers[$notAliasedCacheClearerId] = $allPools;
        }

        foreach ($clearers as $id => $pools) {
            $clearer = $container->getDefinition($id);
            if ($clearer instanceof ChildDefinition) {
                $clearer->replaceArgument(0, $pools);
            } else {
                $clearer->setArgument(0, $pools);
            }
            $clearer->addTag($this->cachePoolClearerTag);

            if ($this->cacheSystemClearerId === $id) {
                $clearer->addTag($this->cacheSystemClearerTag);
            }
        }

        $allPoolsKeys = array_keys($allPools);

        if ($container->hasDefinition('console.command.cache_pool_list')) {
            $container->getDefinition('console.command.cache_pool_list')->replaceArgument(0, $allPoolsKeys);
        }

        if ($container->hasDefinition('console.command.cache_pool_clear')) {
            $container->getDefinition('console.command.cache_pool_clear')->addArgument($allPoolsKeys);
        }

        if ($container->hasDefinition('console.command.cache_pool_delete')) {
            $container->getDefinition('console.command.cache_pool_delete')->addArgument($allPoolsKeys);
        }
    }

    private function getNamespace(string $seed, string $id)
    {
        return substr(str_replace('/', '-', base64_encode(hash('sha256', $id.$seed, true))), 0, 10);
    }

    /**
     * @internal
     */
    public static function getServiceProvider(ContainerBuilder $container, string $name)
    {
        $container->resolveEnvPlaceholders($name, null, $usedEnvs);

        if ($usedEnvs || preg_match('#^[a-z]++:#', $name)) {
            $dsn = $name;

            if (!$container->hasDefinition($name = '.cache_connection.'.ContainerBuilder::hash($dsn))) {
                $definition = new Definition(AbstractAdapter::class);
                $definition->setPublic(false);
                $definition->setFactory([AbstractAdapter::class, 'createConnection']);
                $definition->setArguments([$dsn, ['lazy' => true]]);
                $container->setDefinition($name, $definition);
            }
        }

        return $name;
    }
}
