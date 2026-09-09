<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Translation\DependencyInjection;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Translation\TranslationBundle;

/**
 * Drops the services that the container cannot wire, because what they depend on is missing.
 *
 * @internal
 */
class RemoveMissingDependenciesPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if (!$container->has('profiler')) {
            $container->removeDefinition('data_collector.translation');
            $container->removeDefinition('translator.data_collector');
        }

        if (!$container->hasParameter(TranslationBundle::PROVIDER_LOCALES_PARAMETER)) {
            return;
        }

        // the enabled locales come from another extension, so they can only be read now
        $enabledLocales = $container->hasParameter('kernel.enabled_locales') ? $container->getParameter('kernel.enabled_locales') : [];
        $locales = array_values(array_unique([...$enabledLocales, ...$container->getParameter(TranslationBundle::PROVIDER_LOCALES_PARAMETER)]));

        $container->setParameter(TranslationBundle::PROVIDER_LOCALES_PARAMETER, $locales);
        $container->getDefinition('translation.provider_collection_factory')->replaceArgument(1, $locales);
    }
}
