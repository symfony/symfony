<?php

namespace Symfony\Bundle\FrameworkBundle\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;

/**
 * Adds tagged translation.extractor services to template translation extractor
 */
class TranslationTemplateExtractorPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container)
    {
        if (!$container->hasDefinition('translation.extractor.template')) {
            return;
        }

        $definition = $container->getDefinition('translation.extractor.template');

        foreach ($container->findTaggedServiceIds('translation.extractor') as $id => $attributes) {
            $definition->addMethodCall('addExtractor', array($attributes[0]['alias'], new Reference($id)));
        }
    }
}
