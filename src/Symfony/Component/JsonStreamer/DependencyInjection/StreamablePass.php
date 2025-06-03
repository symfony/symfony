<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\JsonStreamer\DependencyInjection;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Sets the streamable metadata to the services that need them.
 *
 * @author Mathias Arlaud <mathias.arlaud@gmail.com>
 */
class StreamablePass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition('json_streamer.stream_writer')) {
            return;
        }

        $streamable = [];

        // retrieve concrete services tagged with "json_streamer.streamable" tag
        foreach ($container->getDefinitions() as $id => $definition) {
            if (!$tag = ($definition->getTag('json_streamer.streamable')[0] ?? null)) {
                continue;
            }

            if (($className = $container->getDefinition($id)->getClass()) && !$container->getDefinition($id)->isAbstract()) {
                $streamable[$className] = [
                    'object' => $tag['object'],
                    'list' => $tag['list'],
                ];
            }

            $container->removeDefinition($id);
        }

        $container->getDefinition('.json_streamer.cache_warmer.streamer')
            ->replaceArgument(0, $streamable);

        if ($container->hasDefinition('.json_streamer.cache_warmer.lazy_ghost')) {
            $container->getDefinition('.json_streamer.cache_warmer.lazy_ghost')
                ->replaceArgument(0, array_keys($streamable));
        }
    }
}
