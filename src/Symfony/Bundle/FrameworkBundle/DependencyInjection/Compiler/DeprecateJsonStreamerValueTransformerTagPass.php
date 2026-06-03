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
 * @author Mathias Arlaud <mathias.arlaud@gmail.com>
 *
 * @internal to be removed in Symfony 9.0
 */
class DeprecateJsonStreamerValueTransformerTagPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition('json_streamer.stream_writer')) {
            return;
        }

        foreach ($container->findTaggedServiceIds('json_streamer.value_transformer', true) as $id => $_) {
            $definition = $container->getDefinition($id);
            if ($definition->hasTag('json_streamer.property_value_transformer')) {
                continue;
            }

            trigger_deprecation('symfony/json-streamer', '8.1', 'The "json_streamer.value_transformer" tag is deprecated, use "json_streamer.property_value_transformer" instead on service "%s".', $id);

            $definition->addTag('json_streamer.property_value_transformer');
        }
    }
}
