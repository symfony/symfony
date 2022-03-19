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

use Symfony\Component\DependencyInjection\Argument\IteratorArgument;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

/**
 * @author Kevin Bond <kevinbond@gmail.com>
 *
 * @internal
 */
class ConfigureLocaleSwitcherPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if (!$container->has('translation.locale_switcher')) {
            return;
        }

        $localeAwareServices = array_map(
            fn (string $id) => new Reference($id),
            array_keys($container->findTaggedServiceIds('kernel.locale_aware'))
        );

        if ($container->has('translation.locale_aware_request_context')) {
            $localeAwareServices[] = new Reference('translation.locale_aware_request_context');
        }

        $container->getDefinition('translation.locale_switcher')
            ->setArgument(1, new IteratorArgument($localeAwareServices))
        ;
    }
}
