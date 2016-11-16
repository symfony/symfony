<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\DependencyInjection\Compiler;

use Symfony\Component\Cache\Adapter\RedisAdapter;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\DefinitionDecorator;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;
use Symfony\Component\DependencyInjection\Exception\RuntimeException;

/**
 * @author Nicolas Grekas <p@tchwork.com>
 */
class CachePoolPass implements CompilerPassInterface
{
    /**
     * {@inheritdoc}
     */
    public function process(ContainerBuilder $container)
    {
        $namespaceSuffix = '';

        foreach (array('name', 'root_dir', 'environment', 'debug') as $key) {
            if ($container->hasParameter('kernel.'.$key)) {
                $namespaceSuffix .= '.'.$container->getParameter('kernel.'.$key);
            }
        }

        $resolvedPools = array();
        $aliases = $container->getAliases();
        $attributes = array(
            'provider',
            'namespace',
            'default_lifetime',
        );
        foreach ($container->findTaggedServiceIds('cache.pool') as $id => $tags) {
            $pool = $container->getDefinition($id);
            if ($pool->isAbstract()) {
                continue;
            }
            if ($pool instanceof DefinitionDecorator) {
                $resolvedPools[$id] = $pool = $this->resolveAdapters($container, $pool, $id, $tags);
            }
            if (!isset($tags[0]['namespace'])) {
                $tags[0]['namespace'] = $this->getNamespace($namespaceSuffix, $id);
            }
            if (isset($tags[0]['clearer'])) {
                $clearer = strtolower($tags[0]['clearer']);
                while (isset($aliases[$clearer])) {
                    $clearer = (string) $aliases[$clearer];
                }
            } else {
                $clearer = null;
            }
            unset($tags[0]['clearer']);

            if (isset($tags[0]['provider'])) {
                $tags[0]['provider'] = new Reference(static::getServiceProvider($container, $tags[0]['provider']));
            }
            $i = 0;
            foreach ($attributes as $attr) {
                if (isset($tags[0][$attr])) {
                    $pool->replaceArgument($i++, $tags[0][$attr]);
                }
                unset($tags[0][$attr]);
            }
            if (!empty($tags[0])) {
                throw new InvalidArgumentException(sprintf('Invalid "cache.pool" tag for service "%s": accepted attributes are "clearer", "provider", "namespace" and "default_lifetime", found "%s".', $id, implode('", "', array_keys($tags[0]))));
            }

            if (null !== $clearer) {
                $pool->addTag('cache.pool', array('clearer' => $clearer));
            }
        }
        foreach ($resolvedPools as $id => $pool) {
            $container->setDefinition($id, $pool);
        }
    }

    private function getNamespace($namespaceSuffix, $id)
    {
        return substr(str_replace('/', '-', base64_encode(hash('sha256', $id.$namespaceSuffix, true))), 0, 10);
    }

    private function resolveAdapters(ContainerBuilder $container, DefinitionDecorator $pool, $id, array &$tags)
    {
        if (!$container->has($parent = $pool->getParent())) {
            throw new RuntimeException(sprintf('Service "%s": Parent definition "%s" does not exist.', $id, $parent));
        }
        $adapter = $container->findDefinition($parent);
        if ($t = $adapter->getTag('cache.pool')) {
            $tags[0] += $t[0];
        }
        if ($adapter instanceof DefinitionDecorator) {
            $adapter = $this->resolveAdapters($container, $adapter, $parent, $tags);
        }

        return $pool->resolveChanges($adapter);
    }

    /**
     * @internal
     */
    public static function getServiceProvider(ContainerBuilder $container, $name)
    {
        if (0 === strpos($name, 'redis://')) {
            $dsn = $name;

            if (!$container->hasDefinition($name = md5($dsn))) {
                $definition = new Definition(\Redis::class);
                $definition->setPublic(false);
                $definition->setFactory(array(RedisAdapter::class, 'createConnection'));
                $definition->setArguments(array($dsn));
                $container->setDefinition($name, $definition);
            }
        }

        return $name;
    }
}
