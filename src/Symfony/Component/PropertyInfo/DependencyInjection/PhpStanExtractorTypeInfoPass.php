<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\PropertyInfo\DependencyInjection;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

/**
 * @internal
 */
final class PhpStanExtractorTypeInfoPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition('property_info.phpstan_extractor') || !$container->has('type_info.resolver.string') || !$container->has('type_info.type_context_factory')) {
            return;
        }

        $container->getDefinition('property_info.phpstan_extractor')
            ->setArgument('$stringTypeResolver', new Reference('type_info.resolver.string'))
            ->setArgument('$typeContextFactory', new Reference('type_info.type_context_factory'));
    }
}
