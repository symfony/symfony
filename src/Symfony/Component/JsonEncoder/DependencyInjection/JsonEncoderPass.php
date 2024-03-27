<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\JsonEncoder\DependencyInjection;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\JsonEncoder\DecoderInterface;
use Symfony\Component\JsonEncoder\EncoderInterface;

/**
 * Injects encodable classes into services and registers aliases.
 *
 * @author Mathias Arlaud<mathias.arlaud@gmail.com>
 */
final readonly class JsonEncoderPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition('json_encoder.encoder')) {
            return;
        }

        $encodableClassNames = array_map(
            fn (string $id) => $container->getDefinition($id)->getClass(),
            array_keys($container->findTaggedServiceIds('json_encoder.encodable')),
        );

        $container->getDefinition('.json_encoder.cache_warmer.encoder_decoder')
            ->replaceArgument(0, $encodableClassNames);

        $container->getDefinition('.json_encoder.cache_warmer.lazy_ghost')
            ->replaceArgument(0, $encodableClassNames);

        $container->registerAliasForArgument('json_encoder.encoder', EncoderInterface::class, 'json.encoder');
        $container->registerAliasForArgument('json_encoder.decoder', DecoderInterface::class, 'json.decoder');
    }
}
