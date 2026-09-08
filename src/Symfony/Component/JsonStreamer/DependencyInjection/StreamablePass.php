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
 * The cache warmer is dropped when nothing is streamable.
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

        foreach ($container->findTaggedResourceIds('json_streamer.streamable') as $id => $tag) {
            $class = $container->getDefinition($id)->getClass();
            $streamable[$class] = [
                'object' => $tag[0]['object'],
                'list' => $tag[0]['list'],
            ];

            $container->removeDefinition($id);
        }

        if (!$container->hasDefinition('.json_streamer.cache_warmer.streamer')) {
            return;
        }

        if (!$streamable) {
            $container->removeDefinition('.json_streamer.cache_warmer.streamer');

            return;
        }

        $container->getDefinition('.json_streamer.cache_warmer.streamer')
            ->replaceArgument(0, $streamable);
    }
}
