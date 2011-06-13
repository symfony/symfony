<?php

namespace Symfony\Bundle\TwigBundle\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;

/**
 * Adds tagged translation.formatter services to translation writer
 */
class TranslationWriterPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container)
    {
        if (!$container->hasDefinition('twig.translation.writer')) {
            return;
        }

        $definition = $container->getDefinition('twig.translation.writer');

        foreach ($container->findTaggedServiceIds('translation.formatter') as $id => $attributes) {
            $definition->addMethodCall('addFormatter', array($attributes[0]['alias'], new Reference($id)));
        }
    }
}
