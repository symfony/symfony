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
use Symfony\Component\DependencyInjection\Reference;

/**
 * @author Nicolas Grekas <p@tchwork.com>
 */
final class CachePoolClearerPass implements CompilerPassInterface
{
    /**
     * {@inheritdoc}
     */
    public function process(ContainerBuilder $container)
    {
        $container->getParameterBag()->remove('cache.prefix.seed');

        foreach ($container->findTaggedServiceIds('cache.pool') as $id => $attributes) {
            foreach (array_reverse($attributes) as $attr) {
                if (isset($attr['clearer'])) {
                    $clearer = $container->getDefinition($attr['clearer']);
                    $clearer->addMethodCall('addPool', array(new Reference($id)));
                }
                if (array_key_exists('clearer', $attr)) {
                    break;
                }
            }
        }
    }
}
