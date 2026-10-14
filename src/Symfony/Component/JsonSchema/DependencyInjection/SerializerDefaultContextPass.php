<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\JsonSchema\DependencyInjection;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Gives the default context of the serializer to the native class schema resolver, so that dates and UIDs are described as the serializer writes them.
 *
 * @internal
 */
final class SerializerDefaultContextPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition('json_schema.class_schema_resolver.native') || !$container->hasParameter('serializer.default_context')) {
            return;
        }

        $container->getDefinition('json_schema.class_schema_resolver.native')->replaceArgument(0, $container->getParameter('serializer.default_context'));
    }
}
