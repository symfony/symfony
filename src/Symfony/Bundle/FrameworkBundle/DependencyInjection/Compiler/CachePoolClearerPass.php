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

        if (!$container->has('cache.annotations')) {
            return;
        }
        $factory = array(AbstractAdapter::class, 'createSystemCache');
        $annotationsPool = $container->getDefinition('cache.annotations');
        if ($factory !== $annotationsPool->getFactory() || 4 !== count($annotationsPool->getArguments())) {
            return;
        }
        if ($container->has('monolog.logger.cache')) {
            $annotationsPool->addArgument(new Reference('monolog.logger.cache'));
        } elseif ($container->has('cache.system')) {
            $systemPool = $container->getDefinition('cache.system');
            if ($factory === $systemPool->getFactory() && 5 <= count($systemArgs = $systemPool->getArguments())) {
                $annotationsPool->addArgument($systemArgs[4]);
            }
        }
    }
}
