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

/**
 * @internal
 */
class EnabledLocalesPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        $enabledLocales = $container->resolveEnvPlaceholders($container->getParameter('kernel.enabled_locales'), true);
        $container->setParameter('kernel.enabled_locales', $enabledLocales);

        if ($container->hasDefinition('translator.default')) {
            $translator = $container->findDefinition('translator.default');
            $translator->setArgument(5, $enabledLocales);
        }
        if ($container->hasDefinition('routing.loader')) {
            $routing = $container->findDefinition('routing.loader');
            if ($enabledLocales) {
                $routingEnabledLocales = implode('|', array_map('preg_quote', $enabledLocales));
                $routing->replaceArgument(2, ['_locale' => $routingEnabledLocales]);
            }
        }

        $providerDefinitions = [
            'translation.provider_collection_factory' => 1,
            'console.command.translation_pull' => 5,
            'console.command.translation_push' => 3,
        ];

        $locales = $enabledLocales;

        foreach ($providerDefinitions as $definitionId => $argumentIndex) {
            if ($container->hasDefinition($definitionId)) {
                $definition = $container->getDefinition($definitionId);
                $locales = array_merge($locales, $definition->getArgument($argumentIndex));
                break;
            }
        }

        foreach ($providerDefinitions as $definitionId => $argumentIndex) {
            if (!$container->hasDefinition($definitionId)) {
                continue;
            }
            $definition = $container->getDefinition($definitionId);
            $definition->replaceArgument($argumentIndex, $locales);
        }
    }
}
