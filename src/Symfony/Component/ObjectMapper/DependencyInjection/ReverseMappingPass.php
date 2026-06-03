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

/**
 * @author Florent Blaison <florent.blaison@gmail.com>
 */
final class ReverseMappingPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition('object_mapper.metadata_factory.reverse_class')) {
            return;
        }

        $reverseClassObjectMapperMetadataFactory = $container->getDefinition('object_mapper.metadata_factory.reverse_class');

        $classes = [];
        foreach ($container->findTaggedResourceIds('object_mapper.map') as $tags) {
            foreach ($tags as $tag) {
                if (isset($tag['source'], $tag['target'])) {
                    $classes[$tag['source']] = $tag['target'];
                }
            }
        }

        $reverseClassObjectMapperMetadataFactory->replaceArgument(1, $classes);
    }
}
