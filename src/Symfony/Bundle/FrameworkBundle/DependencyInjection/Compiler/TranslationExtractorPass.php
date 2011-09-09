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

use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;

/**
 * Adds tagged translation.extractor services to translation extractor
 */
class TranslationExtractorPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container)
    {
        if (!$container->hasDefinition('translation.extractor')) {
            return;
        }

        $definition = $container->getDefinition('translation.extractor');

        foreach ($container->findTaggedServiceIds('translation.extractor') as $id => $attributes) {
            $definition->addMethodCall('addExtractor', array($attributes[0]['alias'], new Reference($id)));
        }
    }
}
