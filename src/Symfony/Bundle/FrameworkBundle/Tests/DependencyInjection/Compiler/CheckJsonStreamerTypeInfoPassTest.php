<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Tests\DependencyInjection\Compiler;

use PHPUnit\Framework\TestCase;
use Symfony\Bundle\FrameworkBundle\DependencyInjection\Compiler\CheckJsonStreamerTypeInfoPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Exception\RuntimeException;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\JsonStreamer\JsonStreamWriter;
use Symfony\Component\TypeInfo\TypeResolver\TypeResolver;

class CheckJsonStreamerTypeInfoPassTest extends TestCase
{
    public function testNothingIsAddedWhenTypeInfoIsAvailable()
    {
        $container = new ContainerBuilder();
        $container->register('json_streamer.stream_writer', JsonStreamWriter::class);
        $container->register('type_info.resolver', TypeResolver::class);

        new CheckJsonStreamerTypeInfoPass()->process($container);

        $this->assertFalse($container->getDefinition('type_info.resolver')->hasErrors());
        $this->assertFalse($container->hasDefinition('type_info.type_context_factory'));
    }

    public function testNothingIsAddedWhenJsonStreamerIsNotWired()
    {
        $container = new ContainerBuilder();

        new CheckJsonStreamerTypeInfoPass()->process($container);

        $this->assertFalse($container->hasDefinition('type_info.resolver'));
    }

    public function testErrorIsReportedWhenTypeInfoIsMissing()
    {
        $container = new ContainerBuilder();
        $container->register('json_streamer.stream_writer', JsonStreamWriter::class);

        new CheckJsonStreamerTypeInfoPass()->process($container);

        foreach (['type_info.resolver', 'type_info.type_context_factory'] as $id) {
            $this->assertSame(['JsonStreamer support cannot be enabled as the TypeInfo component is not enabled. Try setting "type_info.enabled" to true.'], $container->getDefinition($id)->getErrors());
        }
    }

    public function testTheErrorIsRaisedWhileCompiling()
    {
        $container = new ContainerBuilder();
        $container->register('json_streamer.stream_writer', JsonStreamWriter::class)
            ->setPublic(true)
            ->setArguments([new Reference('type_info.resolver')]);
        $container->addCompilerPass(new CheckJsonStreamerTypeInfoPass());

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('JsonStreamer support cannot be enabled as the TypeInfo component is not enabled. Try setting "type_info.enabled" to true.');

        $container->compile();
    }
}
