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

use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\Adapter\RedisAdapter;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\DefinitionDecorator;
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

        $attributes = array(
            'provider',
            'namespace',
            'default_lifetime',
        );
        foreach ($container->findTaggedServiceIds('cache.pool') as $id => $tags) {
            $adapter = $pool = $container->getDefinition($id);
            if ($pool->isAbstract()) {
                continue;
            }
            while ($adapter instanceof DefinitionDecorator) {
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
                if (isset($tags[0][$attr]) && ('namespace' !== $attr || ArrayAdapter::class !== $adapter->getClass())) {
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

        if (0 === strpos($name, 'redis://') || $usedEnvs) {
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
