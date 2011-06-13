<?php

namespace Symfony\Bundle\TwigBundle\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;

/**
 * Adds tagged translation.loader services to translation extractor
 */
class TranslationExtractorPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container)
    {
        if (!$container->hasDefinition('twig.translation.extractor.file')) {
            return;
        }

        $definition = $container->getDefinition('twig.translation.extractor.file');

        foreach ($container->findTaggedServiceIds('translation.loader') as $id => $attributes) {
            $definition->addMethodCall('addLoader', array($attributes[0]['alias'], new Reference($id)));
        }
    }
}
