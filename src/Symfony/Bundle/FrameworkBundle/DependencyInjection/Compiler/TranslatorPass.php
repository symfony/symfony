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
use Symfony\Component\Finder\Finder;

class TranslatorPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container)
    {
        if (!$container->hasDefinition('translator.default')) {
            return;
        }

        $loaders = array();
        foreach ($container->findTaggedServiceIds('translation.loader') as $id => $attributes) {
            $loaders[$id][] = $attributes[0]['alias'];
            if (isset($attributes[0]['legacy-alias'])) {
                $loaders[$id][] = $attributes[0]['legacy-alias'];
            }
        }

        if ($container->hasDefinition('translation.loader')) {
            $definition = $container->getDefinition('translation.loader');
            foreach ($loaders as $id => $formats) {
                foreach ($formats as $format) {
                    $definition->addMethodCall('addLoader', array($format, new Reference($id)));
                }
            }
        }

        $translatorDefinition = $container->findDefinition('translator.default');
        $translatorDefinition->replaceArgument(2, $loaders);
        if ($container->hasParameter('translator.resource_directories')) {
            $resourceDirs = $container->getParameter('translator.resource_directories');
            $files = array();
            if ($resourceDirs) {
                $finder = Finder::create()
                    ->files()
                    ->filter(function (\SplFileInfo $file) {
                        return 2 === substr_count($file->getBasename(), '.') && preg_match('/\.\w+$/', $file->getBasename());
                    })
                    ->in($resourceDirs)
                ;

                foreach ($finder as $file) {
                    list($domain, $locale, $format) = explode('.', $file->getBasename(), 3);
                    if (!isset($files[$locale])) {
                        $files[$locale] = array();
                    }

                    $files[$locale][] = (string) $file;
                }
            }

            $translatorDefinition->replaceArgument(4, $files);
        }
    }
}
