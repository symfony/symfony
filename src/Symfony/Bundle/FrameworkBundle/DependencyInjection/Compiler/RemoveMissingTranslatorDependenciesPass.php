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
use Symfony\Component\Translation\TranslationBundle;

/**
 * Wires the translation console commands with what TranslationBundle discovered, or drops them.
 *
 * @internal
 */
class RemoveMissingTranslatorDependenciesPass implements CompilerPassInterface
{
    private const COMMANDS = [
        'console.command.translation_debug',
        'console.command.translation_extract',
        'console.command.translation_pull',
        'console.command.translation_push',
        'console.command.translation_lint',
        'console.command.translation_xliff_update_sources',
    ];

    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasParameter(TranslationBundle::TRANS_PATHS_PARAMETER)) {
            foreach (self::COMMANDS as $id) {
                $container->removeDefinition($id);
            }

            return;
        }

        $bag = $container->getParameterBag();
        $transPaths = $bag->resolveValue($container->getParameter(TranslationBundle::TRANS_PATHS_PARAMETER));
        $paths = $bag->resolveValue($container->getParameter(TranslationBundle::PATHS_PARAMETER));
        $defaultPath = $container->getParameter(TranslationBundle::DEFAULT_PATH_PARAMETER);
        $locales = $container->hasParameter(TranslationBundle::PROVIDER_LOCALES_PARAMETER) ? $container->getParameter(TranslationBundle::PROVIDER_LOCALES_PARAMETER) : null;

        if ($container->hasDefinition('console.command.translation_debug')) {
            $container->getDefinition('console.command.translation_debug')->replaceArgument(5, $transPaths);
        }

        if ($container->hasDefinition('console.command.translation_extract')) {
            $container->getDefinition('console.command.translation_extract')->replaceArgument(6, $transPaths);
        }

        if ($container->hasDefinition('console.command.translation_xliff_update_sources')) {
            $container->getDefinition('console.command.translation_xliff_update_sources')->replaceArgument(3, [...$paths, $defaultPath]);
        }

        if (null === $locales) {
            return;
        }

        $container->getDefinition('console.command.translation_pull')
            ->replaceArgument(4, [...$transPaths, $defaultPath])
            ->replaceArgument(5, $locales)
        ;

        $container->getDefinition('console.command.translation_push')
            ->replaceArgument(2, [...$transPaths, $defaultPath])
            ->replaceArgument(3, $locales)
        ;
    }
}
