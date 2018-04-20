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

use Symfony\Component\Cache\Adapter\AbstractAdapter;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;

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
        if ($container->hasParameter('cache.prefix.seed')) {
            $seed = '.'.$container->getParameterBag()->resolveValue($container->getParameter('cache.prefix.seed'));
        } else {
            $seed = '_'.$container->getParameter('kernel.root_dir');
        }
        $seed .= '.'.$container->getParameter('kernel.name').'.'.$container->getParameter('kernel.environment');

        $pools = array();
        $clearers = array();
        $attributes = array(
            'provider',
            'namespace',
            'default_lifetime',
            'reset',
        );
        foreach ($container->findTaggedServiceIds('cache.pool') as $id => $tags) {
            $adapter = $pool = $container->getDefinition($id);
            if ($pool->isAbstract()) {
                continue;
            }
            while ($adapter instanceof ChildDefinition) {
                $adapter = $container->findDefinition($adapter->getParent());
                if ($t = $adapter->getTag('cache.pool')) {
                    $tags[0] += $t[0];
                }
            }
            if (!isset($tags[0]['namespace'])) {
                $tags[0]['namespace'] = $this->getNamespace($seed, $id);
            }
            if (isset($tags[0]['clearer'])) {
                $clearer = $tags[0]['clearer'];
                while ($container->hasAlias($clearer)) {
                    $clearer = (string) $container->getAlias($clearer);
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
                if (!isset($tags[0][$attr])) {
                    // no-op
                } elseif ('reset' === $attr) {
                    if ($tags[0][$attr]) {
                        $pool->addTag('kernel.reset', array('method' => $tags[0][$attr]));
                    }
                } elseif ('namespace' !== $attr || ArrayAdapter::class !== $adapter->getClass()) {
                    $pool->replaceArgument($i++, $tags[0][$attr]);
                }
                unset($tags[0][$attr]);
            }
            if (!empty($tags[0])) {
                throw new InvalidArgumentException(sprintf('Invalid "cache.pool" tag for service "%s": accepted attributes are "clearer", "provider", "namespace", "default_lifetime" and "reset", found "%s".', $id, implode('", "', array_keys($tags[0]))));
            }

            if (null !== $clearer) {
                $clearers[$clearer][$id] = new Reference($id, $container::IGNORE_ON_UNINITIALIZED_REFERENCE);
            }

            $pools[$id] = new Reference($id, $container::IGNORE_ON_UNINITIALIZED_REFERENCE);
        }

        $clearer = 'cache.global_clearer';
        while ($container->hasAlias($clearer)) {
            $clearer = (string) $container->getAlias($clearer);
        }
        if ($container->hasDefinition($clearer)) {
            $clearers['cache.global_clearer'] = $pools;
        }

        foreach ($clearers as $id => $pools) {
            $clearer = $container->getDefinition($id);
            if ($clearer instanceof ChildDefinition) {
                $clearer->replaceArgument(0, $pools);
            } else {
                $clearer->setArgument(0, $pools);
            }
            $clearer->addTag('cache.pool.clearer');

            if ('cache.system_clearer' === $id) {
                $clearer->addTag('kernel.cache_clearer');
            }
        }
    }

    private function getNamespace($seed, $id)
    {
        return substr(str_replace('/', '-', base64_encode(hash('sha256', $id.$seed, true))), 0, 10);
    }

    /**
     * @internal
     */
    public static function getServiceProvider(ContainerBuilder $container, $name)
    {
        $container->resolveEnvPlaceholders($name, null, $usedEnvs);

        if ($usedEnvs || preg_match('#^[a-z]++://#', $name)) {
            $dsn = $name;

            if (!$container->hasDefinition($name = '.cache_connection.'.ContainerBuilder::hash($dsn))) {
                $definition = new Definition(AbstractAdapter::class);
                $definition->setPublic(false);
                $definition->setFactory(array(AbstractAdapter::class, 'createConnection'));
                $definition->setArguments(array($dsn, array('lazy' => true)));
                $container->setDefinition($name, $definition);
            }
        }

        return $name;
    }
}
