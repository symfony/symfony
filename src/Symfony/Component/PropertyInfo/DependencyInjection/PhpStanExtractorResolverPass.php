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
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\TypeInfo\TypeResolver\StringTypeResolver;

/**
 * Gives the PHPStan extractor a resolver of its own when TypeInfoBundle registers none.
 *
 * It resolves no PHPDoc refinements, as there is no "type_info.refinements" option to choose them with.
 *
 * @internal
 */
class PhpStanExtractorResolverPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition('property_info.phpstan_extractor') || $container->has('type_info.resolver.string')) {
            return;
        }

        $container->getDefinition('property_info.phpstan_extractor')
            ->replaceArgument(4, new Definition(StringTypeResolver::class, [null, null, [], false]));
    }
}
