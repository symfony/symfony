<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Serializer\Tests\DependencyInjection;

use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Argument\BoundArgument;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Serializer\Debug\TraceableEncoder;
use Symfony\Component\Serializer\Debug\TraceableNormalizer;
use Symfony\Component\Serializer\DependencyInjection\SerializerPass;

/**
 * Tests for the SerializerPass class.
 *
 * @author Javier Lopez <f12loalf@gmail.com>
 */
class SerializerPassTest extends TestCase
{
    public function testThrowExceptionWhenNoNormalizers()
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('You must tag at least one service as "serializer.normalizer" to use the "serializer" service');
        $container = new ContainerBuilder();
        $container->setParameter('kernel.debug', false);
        $container->register('serializer');

        $serializerPass = new SerializerPass();
        $serializerPass->process($container);
    }

    public function testThrowExceptionWhenNoEncoders()
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('You must tag at least one service as "serializer.encoder" to use the "serializer" service');
        $container = new ContainerBuilder();
        $container->setParameter('kernel.debug', false);
        $container->register('serializer')
            ->addArgument([])
            ->addArgument([]);
        $container->register('normalizer')->addTag('serializer.normalizer');

        $serializerPass = new SerializerPass();
        $serializerPass->process($container);
    }

    public function testServicesAreOrderedAccordingToPriority()
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.debug', false);

        $definition = $container->register('serializer')->setArguments([null, null]);
        $container->register('n2')->addTag('serializer.normalizer', ['priority' => 100])->addTag('serializer.encoder', ['priority' => 100]);
        $container->register('n1')->addTag('serializer.normalizer', ['priority' => 200])->addTag('serializer.encoder', ['priority' => 200]);
        $container->register('n3')->addTag('serializer.normalizer')->addTag('serializer.encoder');

        $serializerPass = new SerializerPass();
        $serializerPass->process($container);

        $expected = [
            new Reference('n1'),
            new Reference('n2'),
            new Reference('n3'),
        ];
        $this->assertEquals($expected, $definition->getArgument(0));
        $this->assertEquals($expected, $definition->getArgument(1));
    }

    public function testBindSerializerDefaultContext()
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.debug', false);
        $container->register('serializer')->setArguments([null, null]);
        $container->setParameter('serializer.default_context', ['enable_max_depth' => true]);
        $definition = $container->register('n1')->addTag('serializer.normalizer')->addTag('serializer.encoder');

        $serializerPass = new SerializerPass();
        $serializerPass->process($container);

        $bindings = $definition->getBindings();
        $this->assertEquals($bindings['array $defaultContext'], new BoundArgument(['enable_max_depth' => true], false));
    }

    public function testNormalizersAndEncodersAreDecoredAndOrderedWhenCollectingData()
    {
        $container = new ContainerBuilder();

        $container->setParameter('kernel.debug', true);
        $container->register('serializer.data_collector');

        $container->register('serializer')->setArguments([null, null]);
        $container->register('n')->addTag('serializer.normalizer');
        $container->register('e')->addTag('serializer.encoder');

        $serializerPass = new SerializerPass();
        $serializerPass->process($container);

        $traceableNormalizerDefinition = $container->getDefinition('debug.n');
        $traceableEncoderDefinition = $container->getDefinition('debug.e');

        $this->assertEquals(TraceableNormalizer::class, $traceableNormalizerDefinition->getClass());
        $this->assertEquals(['n', null, 0], $traceableNormalizerDefinition->getDecoratedService());
        $this->assertEquals(new Reference('debug.n.inner'), $traceableNormalizerDefinition->getArgument(0));
        $this->assertEquals(new Reference('serializer.data_collector'), $traceableNormalizerDefinition->getArgument(1));

        $this->assertEquals(TraceableEncoder::class, $traceableEncoderDefinition->getClass());
        $this->assertEquals(['e', null, 0], $traceableEncoderDefinition->getDecoratedService());
        $this->assertEquals(new Reference('debug.e.inner'), $traceableEncoderDefinition->getArgument(0));
        $this->assertEquals(new Reference('serializer.data_collector'), $traceableEncoderDefinition->getArgument(1));
    }
}
