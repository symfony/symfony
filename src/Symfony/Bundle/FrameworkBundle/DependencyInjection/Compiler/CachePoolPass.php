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

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\DefinitionDecorator;
use Symfony\Component\DependencyInjection\Reference;

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
            if (!isset($tags[0]['namespace'])) {
                $tags[0]['namespace'] = $this->getNamespace($id);
            }
            while ($adapter instanceof DefinitionDecorator) {
                $adapter = $container->findDefinition($adapter->getParent());
                if ($t = $adapter->getTag('cache.pool')) {
                    $tags[0] += $t[0];
                }
            }
            if (isset($tags[0]['clearer'])) {
                $clearer = $container->getDefinition($tags[0]['clearer']);
            } else {
                $clearer = null;
            }
            unset($tags[0]['clearer']);

            if (isset($tags[0]['provider']) && is_string($tags[0]['provider'])) {
                $tags[0]['provider'] = new Reference($tags[0]['provider']);
            }
            $i = 0;
            foreach ($attributes as $attr) {
                if (isset($tags[0][$attr])) {
                    $pool->replaceArgument($i++, $tags[0][$attr]);
                }
                unset($tags[0][$attr]);
            }
            if (!empty($tags[0])) {
                throw new \InvalidArgumentException(sprintf('Invalid "cache.pool" tag for service "%s": accepted attributes are "provider", "namespace" and "default_lifetime", found "%s".', $id, implode('", "', array_keys($tags[0]))));
            }

            if (null !== $clearer) {
                $clearer->addMethodCall('addPool', array(new Reference($id)));
            }
        }
    }

    private function getNamespace($id)
    {
        return substr(str_replace('/', '-', base64_encode(md5('symfony.'.$id, true))), 0, 10);
    }
}
