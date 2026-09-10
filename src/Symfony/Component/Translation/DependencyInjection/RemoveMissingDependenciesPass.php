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

/**
 * Drops the services that the container cannot wire, because what they depend on is missing.
 *
 * @internal
 */
class RemoveMissingDependenciesPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasParameter('.translator.provider_locales')) {
            return;
        }

        // the enabled locales come from another extension, so they can only be read now
        $enabledLocales = $container->hasParameter('kernel.enabled_locales') ? $container->getParameter('kernel.enabled_locales') : [];
        $locales = array_values(array_unique([...$enabledLocales, ...$container->getParameter('.translator.provider_locales')]));
        $container->getParameterBag()->remove('.translator.provider_locales');

        $container->getDefinition('translation.provider_collection_factory')->replaceArgument(1, $locales);

        foreach (['console.command.translation_pull' => 5, 'console.command.translation_push' => 3] as $id => $argument) {
            if ($container->hasDefinition($id)) {
                $container->getDefinition($id)->replaceArgument($argument, $locales);
            }
        }
    }
}
