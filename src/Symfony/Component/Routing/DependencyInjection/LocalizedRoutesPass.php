<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Routing\DependencyInjection;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;

/**
 * Constrains the "_locale" placeholder to the locales the kernel enables.
 *
 * The list is a kernel-wide setting that another bundle owns, so it can only be read once
 * every extension has been loaded.
 *
 * @internal
 */
class LocalizedRoutesPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition('routing.loader') || !$container->hasParameter('kernel.enabled_locales')) {
            return;
        }

        if (!$enabledLocales = $container->getParameter('kernel.enabled_locales')) {
            return;
        }

        $usedEnvs = [];
        $container->resolveEnvPlaceholders($enabledLocales, null, $usedEnvs);

        if (!$usedEnvs) {
            $locales = implode('|', array_map('preg_quote', $enabledLocales));
        } else {
            $locales = new Definition('string')
                ->setFactory('implode')
                ->setArguments(['|', new Definition('array')
                    ->setFactory('array_map')
                    ->setArguments(['preg_quote', $enabledLocales]),
                ]);
        }

        $container->getDefinition('routing.loader')->replaceArgument(2, ['_locale' => $locales]);
    }
}
