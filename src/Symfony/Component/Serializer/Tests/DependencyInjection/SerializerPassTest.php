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
use Symfony\Component\Serializer\Debug\TraceableSerializer;
use Symfony\Component\Serializer\DependencyInjection\SerializerPass;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * Tests for the SerializerPass class.
 *
 * @author Javier Lopez <f12loalf@gmail.com>
 */
class SerializerPassTest extends TestCase
{
    public function testThrowExceptionWhenNoNormalizers()
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.debug', false);
        $container->register('serializer');

        $serializerPass = new SerializerPass();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('You must tag at least one service as "serializer.normalizer" to use the "serializer" service');

        $serializerPass->process($container);
    }

    public function testThrowExceptionWhenNoEncoders()
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.debug', false);
        $container->register('serializer')
            ->addArgument([])
            ->addArgument([]);
        $container->register('normalizer')->addTag('serializer.normalizer');

        $serializerPass = new SerializerPass();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('You must tag at least one service as "serializer.encoder" to use the "serializer" service');

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
        $context = ['enable_max_depth' => true];

        $container = new ContainerBuilder();
        $container->setParameter('kernel.debug', false);
        $container->register('serializer')->setArguments([null, null, []]);
        $container->setParameter('serializer.default_context', ['enable_max_depth' => true]);
        $definition = $container->register('n1')->addTag('serializer.normalizer')->addTag('serializer.encoder');

        $serializerPass = new SerializerPass();
        $serializerPass->process($container);

        $bindings = $definition->getBindings();
        $this->assertEquals($bindings['array $defaultContext'], new BoundArgument($context, false));
        $this->assertEquals($context, $container->getDefinition('serializer')->getArgument('$defaultContext'));
    }

    public function testNormalizersAndEncodersAreDecoratedAndOrderedWhenCollectingData()
    {
        $container = new ContainerBuilder();

        $container->setParameter('kernel.debug', true);
        $container->register('serializer.data_collector');

        $container->register('serializer')->setArguments([null, null]);
        $container->register('n')->addTag('serializer.normalizer');
        $container->register('e')->addTag('serializer.encoder');

        $serializerPass = new SerializerPass();
        $serializerPass->process($container);

        $traceableNormalizerDefinition = $container->getDefinition('.debug.serializer.normalizer.n');
        $traceableEncoderDefinition = $container->getDefinition('.debug.serializer.encoder.e');

        $this->assertEquals(TraceableNormalizer::class, $traceableNormalizerDefinition->getClass());
        $this->assertEquals(new Reference('n'), $traceableNormalizerDefinition->getArgument(0));
        $this->assertEquals(new Reference('serializer.data_collector'), $traceableNormalizerDefinition->getArgument(1));
        $this->assertSame('default', $traceableNormalizerDefinition->getArgument(2));

        $this->assertEquals(TraceableEncoder::class, $traceableEncoderDefinition->getClass());
        $this->assertEquals(new Reference('e'), $traceableEncoderDefinition->getArgument(0));
        $this->assertEquals(new Reference('serializer.data_collector'), $traceableEncoderDefinition->getArgument(1));
        $this->assertSame('default', $traceableEncoderDefinition->getArgument(2));
    }

    /**
     * @dataProvider provideDefaultSerializerTagsData
     */
    public function testDefaultSerializerTagsAreResolvedCorrectly(
        array $normalizerTagAttributes,
        array $encoderTagAttributes,
        array $expectedNormalizerTags,
        array $expectedEncoderTags,
    ) {
        $container = new ContainerBuilder();

        $container->setParameter('kernel.debug', false);
        $container->setParameter('.serializer.named_serializers', []);

        $container->register('serializer')->setArguments([null, null]);
        $container->register('n0')->addTag('serializer.normalizer', ['serializer' => 'default']);
        $container->register('e0')->addTag('serializer.encoder', ['serializer' => 'default']);

        $normalizerDefinition = $container->register('n1')->addTag('serializer.normalizer', $normalizerTagAttributes);
        $encoderDefinition = $container->register('e1')->addTag('serializer.encoder', $encoderTagAttributes);

        $serializerPass = new SerializerPass();
        $serializerPass->process($container);

        $this->assertSame($expectedNormalizerTags, $normalizerDefinition->getTag('serializer.normalizer.default'));
        $this->assertSame($expectedEncoderTags, $encoderDefinition->getTag('serializer.encoder.default'));
    }

    public static function provideDefaultSerializerTagsData(): iterable
    {
        yield 'include no name' => [
            [],
            [],
            [[]],
            [[]],
        ];

        yield 'include name' => [
            ['serializer' => 'default'],
            ['serializer' => 'default'],
            [[]],
            [[]],
        ];

        yield 'include built-in with different name' => [
            ['built_in' => true, 'serializer' => 'api'],
            ['built_in' => true, 'serializer' => 'api'],
            [[]],
            [[]],
        ];

        yield 'include no name with priority' => [
            ['priority' => 200],
            ['priority' => 100],
            [['priority' => 200]],
            [['priority' => 100]],
        ];

        yield 'include name with priority' => [
            ['serializer' => 'default', 'priority' => 200],
            ['serializer' => 'default', 'priority' => 100],
            [['priority' => 200]],
            [['priority' => 100]],
        ];

        yield 'include wildcard' => [
            ['serializer' => '*'],
            ['serializer' => '*'],
            [[]],
            [[]],
        ];

        yield 'is unique when built-in with name' => [
            ['built_in' => true, 'serializer' => 'default'],
            ['built_in' => true, 'serializer' => 'default'],
            [[]],
            [[]],
        ];

        yield 'do not include different name' => [
            ['serializer' => 'api'],
            ['serializer' => 'api'],
            [],
            [],
        ];
    }

    /**
     * @dataProvider provideNamedSerializerTagsData
     */
    public function testNamedSerializerTagsAreResolvedCorrectly(
        array $config,
        array $normalizerTagAttributes,
        array $encoderTagAttributes,
        array $expectedNormalizerTags,
        array $expectedEncoderTags,
    ) {
        $container = new ContainerBuilder();

        $container->setParameter('kernel.debug', false);
        $container->setParameter('.serializer.named_serializers', ['api' => $config]);

        $container->register('serializer')->setArguments([null, null]);
        $container->register('n0')->addTag('serializer.normalizer', ['serializer' => ['default', 'api']]);
        $container->register('e0')->addTag('serializer.encoder', ['serializer' => ['default', 'api']]);

        $normalizerDefinition = $container->register('n1')->addTag('serializer.normalizer', $normalizerTagAttributes);
        $encoderDefinition = $container->register('e1')->addTag('serializer.encoder', $encoderTagAttributes);

        $serializerPass = new SerializerPass();
        $serializerPass->process($container);

        $this->assertSame($expectedNormalizerTags, $normalizerDefinition->getTag('serializer.normalizer.api'));
        $this->assertSame($expectedEncoderTags, $encoderDefinition->getTag('serializer.encoder.api'));
    }

    public static function provideNamedSerializerTagsData(): iterable
    {
        yield 'include built-in' => [
            ['include_built_in_normalizers' => true, 'include_built_in_encoders' => true],
            ['built_in' => true],
            ['built_in' => true],
            [[]],
            [[]],
        ];

        yield 'include built-in normalizers only' => [
            ['include_built_in_normalizers' => true, 'include_built_in_encoders' => false],
            ['built_in' => true],
            ['built_in' => true],
            [[]],
            [],
        ];

        yield 'include built-in encoders only' => [
            ['include_built_in_normalizers' => false, 'include_built_in_encoders' => true],
            ['built_in' => true],
            ['built_in' => true],
            [],
            [[]],
        ];

        yield 'include name' => [
            ['include_built_in_normalizers' => false, 'include_built_in_encoders' => false],
            ['serializer' => 'api'],
            ['serializer' => 'api'],
            [[]],
            [[]],
        ];

        yield 'include name with priority' => [
            ['include_built_in_normalizers' => false, 'include_built_in_encoders' => false],
            ['serializer' => 'api', 'priority' => 200],
            ['serializer' => 'api', 'priority' => 100],
            [['priority' => 200]],
            [['priority' => 100]],
        ];

        yield 'include wildcard' => [
            ['include_built_in_normalizers' => false, 'include_built_in_encoders' => false],
            ['serializer' => '*'],
            ['serializer' => '*'],
            [[]],
            [[]],
        ];

        yield 'do not include when include built-in not set' => [
            [],
            ['built_in' => true],
            ['built_in' => true],
            [],
            [],
        ];

        yield 'do not include not built-in and no name' => [
            ['include_built_in_normalizers' => false, 'include_built_in_encoders' => false],
            [],
            [],
            [],
            [],
        ];

        yield 'do not include different name' => [
            ['include_built_in_normalizers' => false, 'include_built_in_encoders' => false],
            ['serializer' => 'api2'],
            ['serializer' => 'api2'],
            [],
            [],
        ];
    }

    public function testMultipleNamedSerializerTagsAreResolvedCorrectly()
    {
        $container = new ContainerBuilder();

        $container->setParameter('kernel.debug', false);
        $container->setParameter('.serializer.named_serializers', [
            'api' => [],
            'api2' => [],
        ]);

        $container->register('serializer')->setArguments([null, null]);
        $container->register('n0')->addTag('serializer.normalizer', ['serializer' => 'default']);
        $container->register('e0')->addTag('serializer.encoder', ['serializer' => 'default']);

        $normalizerDefinition = $container->register('n1')->addTag('serializer.normalizer', ['serializer' => ['api', 'api2']]);
        $encoderDefinition = $container->register('e1')
            ->addTag('serializer.encoder', ['serializer' => ['api', 'api2']])
            ->addTag('serializer.encoder', ['serializer' => ['api', 'api2'], 'priority' => 100])
        ;

        $serializerPass = new SerializerPass();
        $serializerPass->process($container);

        $this->assertTrue($normalizerDefinition->hasTag('serializer.normalizer.api'));
        $this->assertCount(1, $normalizerDefinition->getTag('serializer.normalizer.api'));
        $this->assertTrue($normalizerDefinition->hasTag('serializer.normalizer.api2'));
        $this->assertCount(1, $normalizerDefinition->getTag('serializer.normalizer.api2'));

        $this->assertTrue($encoderDefinition->hasTag('serializer.encoder.api'));
        $this->assertCount(2, $encoderDefinition->getTag('serializer.encoder.api'));
        $this->assertTrue($encoderDefinition->hasTag('serializer.encoder.api2'));
        $this->assertCount(2, $encoderDefinition->getTag('serializer.encoder.api2'));
    }

    public function testThrowExceptionWhenNoNormalizersForNamedSerializers()
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.debug', false);
        $container->setParameter('.serializer.named_serializers', [
            'api' => [],
        ]);

        $container->register('serializer')->setArguments([null, null]);
        $container->register('n0')->addTag('serializer.normalizer');
        $container->register('e0')->addTag('serializer.encoder', ['serializer' => '*']);

        $serializerPass = new SerializerPass();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('The named serializer "api" requires at least one registered normalizer. Tag the normalizers as "serializer.normalizer" with the "serializer" attribute set to "api".');

        $serializerPass->process($container);
    }

    public function testThrowExceptionWhenNoEncodersForNamedSerializers()
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.debug', false);
        $container->setParameter('.serializer.named_serializers', [
            'api' => [],
        ]);

        $container->register('serializer')->setArguments([null, null]);
        $container->register('n0')->addTag('serializer.normalizer', ['serializer' => '*']);
        $container->register('e0')->addTag('serializer.encoder');

        $serializerPass = new SerializerPass();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('The named serializer "api" requires at least one registered encoder. Tag the encoders as "serializer.encoder" with the "serializer" attribute set to "api".');

        $serializerPass->process($container);
    }

    /**
     * @testWith [null]
     *           ["some.converter"]
     */
    public function testChildNameConverterIsNotBuiltWhenExpected(?string $nameConverter)
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.debug', false);
        $container->setParameter('.serializer.name_converter', $nameConverter);
        $container->setParameter('.serializer.named_serializers', [
            'api' => ['name_converter' => $nameConverter],
        ]);

        $container->register('serializer')->setArguments([null, null]);
        $container->register('n')->addTag('serializer.normalizer', ['serializer' => '*']);
        $container->register('e')->addTag('serializer.encoder', ['serializer' => '*']);

        $serializerPass = new SerializerPass();
        $serializerPass->process($container);

        $this->assertFalse($container->hasDefinition('serializer.name_converter.metadata_aware.'.ContainerBuilder::hash($nameConverter)));
    }

    /**
     * @dataProvider provideChildNameConverterCases
     */
    public function testChildNameConverterIsBuiltWhenExpected(
        ?string $defaultSerializerNameConverter,
        ?string $namedSerializerNameConverter,
        string $nameConverterIdExists,
        string $nameConverterIdDoesNotExist,
        array $nameConverterArguments,
    ) {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.debug', false);
        $container->setParameter('.serializer.name_converter', $defaultSerializerNameConverter);
        $container->setParameter('.serializer.named_serializers', [
            'api' => ['name_converter' => $namedSerializerNameConverter],
        ]);

        $container->register('serializer')->setArguments([null, null]);
        $container->register('n')->addTag('serializer.normalizer', ['serializer' => '*']);
        $container->register('e')->addTag('serializer.encoder', ['serializer' => '*']);

        $serializerPass = new SerializerPass();
        $serializerPass->process($container);

        $this->assertFalse($container->hasDefinition($nameConverterIdExists));
        $this->assertTrue($container->hasDefinition($nameConverterIdDoesNotExist));
        $this->assertEquals($nameConverterArguments, $container->getDefinition($nameConverterIdDoesNotExist)->getArguments());
    }

    public static function provideChildNameConverterCases(): iterable
    {
        $withNull = 'serializer.name_converter.metadata_aware.'.ContainerBuilder::hash(null);
        $withConverter = 'serializer.name_converter.metadata_aware.'.ContainerBuilder::hash('some.converter');

        yield [null, 'some.converter', $withNull, $withConverter, [new Reference('some.converter')]];
        yield ['some.converter', null, $withConverter, $withNull, []];
    }

    /**
     * @dataProvider provideDifferentNamedSerializerConfigsCases
     */
    public function testNamedSerializersCreateNewServices(
        array $defaultSerializerDefaultContext,
        ?string $defaultSerializerNameConverter,
        array $namedSerializerConfig,
        string $nameConverterId,
    ) {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.debug', false);
        $container->setParameter('serializer.default_context', $defaultSerializerDefaultContext);
        $container->setParameter('.serializer.name_converter', $defaultSerializerNameConverter);
        $container->setParameter('.serializer.named_serializers', [
            'api' => $namedSerializerConfig,
        ]);

        $container->register('serializer')->setArguments([null, null]);
        $container->register('n')
            ->addArgument(new Reference('serializer.name_converter.metadata_aware'))
            ->addTag('serializer.normalizer', ['serializer' => '*'])
        ;
        $container->register('e')
            ->addArgument(new Reference('serializer.name_converter.metadata_aware'))
            ->addTag('serializer.encoder', ['serializer' => '*'])
        ;

        $serializerPass = new SerializerPass();
        $serializerPass->process($container);

        $this->assertEquals([new Reference('n.api')], $container->getDefinition('serializer.api')->getArgument(0));
        $this->assertEquals(new Reference($nameConverterId), $container->getDefinition('n.api')->getArgument(0));
        $this->assertEquals([new Reference('e.api')], $container->getDefinition('serializer.api')->getArgument(1));
        $this->assertEquals(new Reference($nameConverterId), $container->getDefinition('e.api')->getArgument(0));
    }

    public static function provideDifferentNamedSerializerConfigsCases(): iterable
    {
        yield [
            ['a' => true, 'b' => 3],
            null,
            ['default_context' => ['c' => 3, 'a' => true]],
            'serializer.name_converter.metadata_aware',
        ];
        yield [
            [],
            'some.converter',
            ['name_converter' => null],
            'serializer.name_converter.metadata_aware.'.ContainerBuilder::hash(null),
        ];
        yield [
            ['a' => true, 'b' => 3],
            null,
            ['default_context' => ['c' => 3, 'a' => true], 'name_converter' => 'some.converter'],
            'serializer.name_converter.metadata_aware.'.ContainerBuilder::hash('some.converter'),
        ];
    }

    public function testServicesAreOrderedAccordingToPriorityForNamedSerializers()
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.debug', false);
        $container->setParameter('.serializer.named_serializers', [
            'api' => [],
        ]);

        $container->register('serializer')->setArguments([null, null]);
        $container->register('n2')
            ->addTag('serializer.normalizer', ['serializer' => '*', 'priority' => 100])
            ->addTag('serializer.encoder', ['serializer' => '*', 'priority' => 100])
        ;
        $container->register('n1')
            ->addTag('serializer.normalizer', ['serializer' => 'api', 'priority' => 200])
            ->addTag('serializer.encoder', ['serializer' => 'api', 'priority' => 200])
        ;
        $container->register('n3')
            ->addTag('serializer.normalizer', ['serializer' => 'api'])
            ->addTag('serializer.encoder', ['serializer' => 'api'])
        ;

        $serializerPass = new SerializerPass();
        $serializerPass->process($container);

        $this->assertTrue($container->hasDefinition('serializer.api'));
        $definition = $container->getDefinition('serializer.api');

        $expected = [
            new Reference('n1.api'),
            new Reference('n2.api'),
            new Reference('n3.api'),
        ];
        $this->assertEquals($expected, $definition->getArgument(0));
        $this->assertEquals($expected, $definition->getArgument(1));
    }

    public function testBindSerializerDefaultContextToNamedSerializers()
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.debug', false);
        $container->setParameter('.serializer.named_serializers', [
            'api' => ['default_context' => $defaultContext = ['enable_max_depth' => true]],
        ]);

        $container->register('serializer')->setArguments([null, null, []]);
        $definition = $container->register('n1')
            ->addTag('serializer.normalizer', ['serializer' => '*'])
            ->addTag('serializer.encoder', ['serializer' => '*'])
        ;

        $serializerPass = new SerializerPass();
        $serializerPass->process($container);

        $this->assertSame([], $definition->getBindings());

        $bindings = $container->getDefinition('n1.api')->getBindings();
        $this->assertArrayHasKey('array $defaultContext', $bindings);
        $this->assertEquals($bindings['array $defaultContext'], new BoundArgument($defaultContext, false));
        $this->assertArrayNotHasKey('$defaultContext', $container->getDefinition('serializer')->getArguments());
        $this->assertEquals($defaultContext, $container->getDefinition('serializer.api')->getArgument('$defaultContext'));
    }

    public function testNamedSerializersAreRegistered()
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.debug', false);
        $container->setParameter('.serializer.named_serializers', [
            'api' => [],
            'api2' => [],
        ]);

        $container->register('serializer')->setArguments([null, null]);
        $container->register('n')->addTag('serializer.normalizer', ['serializer' => '*']);
        $container->register('e')->addTag('serializer.encoder', ['serializer' => '*']);

        $serializerPass = new SerializerPass();
        $serializerPass->process($container);

        $this->assertFalse($container->hasAlias(\sprintf('%s $defaultSerializer', SerializerInterface::class)));

        $this->assertTrue($container->hasDefinition('serializer.api'));
        $this->assertTrue($container->hasAlias(\sprintf('%s $apiSerializer', SerializerInterface::class)));
        $this->assertTrue($container->hasDefinition('serializer.api2'));
        $this->assertTrue($container->hasAlias(\sprintf('%s $api2Serializer', SerializerInterface::class)));
        $this->assertTrue($container->hasAlias(\sprintf('%s $api2Normalizer', NormalizerInterface::class)));
        $this->assertTrue($container->hasAlias(\sprintf('%s $api2Denormalizer', DenormalizerInterface::class)));
    }

    public function testNormalizersAndEncodersAreDecoratedAndOrderedWhenCollectingDataForNamedSerializers()
    {
        $container = new ContainerBuilder();

        $container->setParameter('kernel.debug', true);
        $container->setParameter('.serializer.named_serializers', [
            'api' => ['default_context' => ['enable_max_depth' => true]],
        ]);
        $container->register('serializer.data_collector');

        $container->register('serializer')->setArguments([null, null]);
        $container->register('n')->addTag('serializer.normalizer', ['serializer' => '*']);
        $container->register('e')->addTag('serializer.encoder', ['serializer' => '*']);

        $container->register('debug.serializer', TraceableSerializer::class)
            ->setDecoratedService('serializer')
            ->setArguments([
                new Reference('debug.serializer.inner'),
                new Reference('serializer.data_collector'),
                'default',
            ])
        ;

        $serializerPass = new SerializerPass();
        $serializerPass->process($container);

        $traceableNormalizerDefinition = $container->getDefinition('.debug.serializer.normalizer.n.api');
        $traceableEncoderDefinition = $container->getDefinition('.debug.serializer.encoder.e.api');

        $traceableSerializerDefinition = $container->getDefinition('debug.serializer.api');
        $this->assertSame('serializer.api', $traceableSerializerDefinition->getDecoratedService()[0]);
        $this->assertEquals(new Reference('debug.serializer.api.inner'), $traceableSerializerDefinition->getArgument(0));
        $this->assertSame('api', $traceableSerializerDefinition->getArgument(2));

        $this->assertEquals(TraceableNormalizer::class, $traceableNormalizerDefinition->getClass());
        $this->assertEquals(new Reference('n.api'), $traceableNormalizerDefinition->getArgument(0));
        $this->assertEquals(new Reference('serializer.data_collector'), $traceableNormalizerDefinition->getArgument(1));
        $this->assertSame('api', $traceableNormalizerDefinition->getArgument(2));

        $this->assertEquals(TraceableEncoder::class, $traceableEncoderDefinition->getClass());
        $this->assertEquals(new Reference('e.api'), $traceableEncoderDefinition->getArgument(0));
        $this->assertEquals(new Reference('serializer.data_collector'), $traceableEncoderDefinition->getArgument(1));
        $this->assertSame('api', $traceableEncoderDefinition->getArgument(2));
    }
}
