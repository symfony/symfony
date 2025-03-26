<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\JsonStreamer\Tests\DependencyInjection;

use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\JsonStreamer\DependencyInjection\StreamablePass;

class StreamablePassTest extends TestCase
{
    public function testSetStreamable()
    {
        $container = new ContainerBuilder();

        $container->register('json_streamer.stream_writer');
        $container->register('.json_streamer.cache_warmer.streamer')->setArguments([null]);
        $container->register('.json_streamer.cache_warmer.lazy_ghost')->setArguments([null]);

        $container->register('streamable')->setClass('Foo')->addTag('json_streamer.streamable', ['object' => true, 'list' => true]);
        $container->register('abstractStreamable')->setClass('Bar')->addTag('json_streamer.streamable', ['object' => true, 'list' => true])->setAbstract(true);
        $container->register('notStreamable')->setClass('Baz');

        $pass = new StreamablePass();
        $pass->process($container);

        $streamerCacheWarmer = $container->getDefinition('.json_streamer.cache_warmer.streamer');
        $lazyGhostCacheWarmer = $container->getDefinition('.json_streamer.cache_warmer.lazy_ghost');

        $this->assertSame(['Foo' => ['object' => true, 'list' => true]], $streamerCacheWarmer->getArgument(0));
        $this->assertSame(['Foo'], $lazyGhostCacheWarmer->getArgument(0));
    }
}
