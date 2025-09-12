<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\ObjectMapper\DependencyInjection;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;

/**
 * @author Antoine Bluchet <soyuka@gmail.com>
 */
final class AttributeMetadataPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        $warmerServiceId = 'object_mapper.cached.cache_warmer';
        if (!$container->hasDefinition($warmerServiceId)) {
            return;
        }

        $mappedPairs = [];
        $resolve = $container->getParameterBag()->resolveValue(...);
        foreach ($container->getDefinitions() as $id => $definition) {
            if (!$tags = $definition->getTag('object_mapper.attribute_metadata')) {
                continue;
            }

            if (!$definition->hasTag('container.excluded')) {
                throw new InvalidArgumentException(\sprintf('The resource "%s" with a "Map" attribute must be tagged with "container.excluded".', $id));
            }

            foreach ($tags as $tag) {
                if (!isset($tag['source']) || !isset($tag['target'])) {
                    continue;
                }

                $source = $resolve($tag['source']);
                $target = $resolve($tag['target']);

                if (class_exists($source) && class_exists($target)) {
                    $mappedPairs[] = ['source' => $source, 'target' => $target];
                }
            }

            $container->removeDefinition($id);
        }

        if (!$mappedPairs) {
            return;
        }

        $container->getDefinition($warmerServiceId)
            ->replaceArgument(0, $mappedPairs);
    }
}
