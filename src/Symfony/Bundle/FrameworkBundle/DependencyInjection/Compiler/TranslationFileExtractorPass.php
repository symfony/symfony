<?php

namespace Symfony\Bundle\FrameworkBundle\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;

/**
 * Adds tagged translation.loader services to file translation extractor
 */
class TranslationFileExtractorPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container)
    {
        if (!$container->hasDefinition('translation.extractor.file')) {
            return;
        }

        $definition = $container->getDefinition('translation.extractor.file');

        foreach ($container->findTaggedServiceIds('translation.loader') as $id => $attributes) {
            $definition->addMethodCall('addLoader', array($attributes[0]['alias'], new Reference($id)));
        }
    }
}
