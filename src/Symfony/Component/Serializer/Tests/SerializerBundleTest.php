<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Serializer\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\Compiler\MergeExtensionConfigurationPass;
use Symfony\Component\DependencyInjection\Compiler\RemoveMissingDependenciesPass as ContainerRemoveMissingDependenciesPass;
use Symfony\Component\DependencyInjection\Compiler\ResolveBindingsPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ParameterBag\EnvPlaceholderParameterBag;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Serializer\DependencyInjection\RemoveMissingDependenciesPass;
use Symfony\Component\Serializer\DependencyInjection\SerializerPass;
use Symfony\Component\Serializer\Encoder\JsonDecode;
use Symfony\Component\Serializer\Normalizer\BackedEnumNormalizer;
use Symfony\Component\Serializer\Normalizer\ConstraintViolationListNormalizer;
use Symfony\Component\Serializer\Normalizer\DataUriNormalizer;
use Symfony\Component\Serializer\Normalizer\DateIntervalNormalizer;
use Symfony\Component\Serializer\Normalizer\DateTimeNormalizer;
use Symfony\Component\Serializer\Normalizer\FormErrorNormalizer;
use Symfony\Component\Serializer\Normalizer\JsonSerializableNormalizer;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Normalizer\TranslatableNormalizer;
use Symfony\Component\Serializer\SerializerBundle;

class SerializerBundleTest extends TestCase
{
    public function testSerializerDisabled()
    {
        $container = $this->load([]);
        // the bundle is registered on its own now, so the serializer is on unless it is turned off
        $this->assertTrue($container->has('serializer'));
    }

    public function testSerializerEnabled()
    {
        $container = $this->load(['enable_attributes' => true, 'name_converter' => 'serializer.name_converter.camel_case_to_snake_case', 'circular_reference_handler' => 'my.circular.reference.handler', 'max_depth_handler' => 'my.max.depth.handler', 'default_context' => ['enable_max_depth' => true], 'named_serializers' => ['api' => ['include_built_in_normalizers' => true, 'include_built_in_encoders' => true, 'default_context' => ['enable_max_depth' => false]]]]);
        $this->assertTrue($container->has('serializer'));

        $argument = $container->getDefinition('serializer.mapping.chain_loader')->getArgument(0);

        // discovery of per-bundle mapping files is covered by FrameworkExtensionTestCase::testSerializerMapping,
        // where the bundle metadata and the files it scans for live
        $this->assertCount(1, $argument);
        $this->assertEquals(new Reference('serializer.mapping.attribute_loader'), $argument[0]);
        $this->assertEquals(new Reference('serializer.name_converter.camel_case_to_snake_case'), $container->getDefinition('serializer.name_converter.metadata_aware')->getArgument(1));
        $this->assertEquals(new Reference('property_info', ContainerBuilder::IGNORE_ON_INVALID_REFERENCE), $container->getDefinition('serializer.normalizer.object')->getArgument(3));
    }

    public function testSerializerWithoutTranslator()
    {
        $container = $this->load(['enabled' => true], translator: false);
        $this->assertFalse($container->hasDefinition('serializer.normalizer.translatable'));
    }

    public function testSerializerDefaultParameters()
    {
        $container = $this->load(['enabled' => true]);
        $this->assertFalse($container->hasParameter('.serializer.name_converter'));
        $this->assertFalse($container->hasParameter('serializer.default_context'));
        $this->assertTrue($container->hasParameter('.serializer.named_serializers'));
        $this->assertSame([], $container->getParameter('.serializer.named_serializers'));
    }

    public function testSerializerParametersAreSet()
    {
        $container = $this->load(['enable_attributes' => true, 'name_converter' => 'serializer.name_converter.camel_case_to_snake_case', 'circular_reference_handler' => 'my.circular.reference.handler', 'max_depth_handler' => 'my.max.depth.handler', 'default_context' => ['enable_max_depth' => true], 'named_serializers' => ['api' => ['include_built_in_normalizers' => true, 'include_built_in_encoders' => true, 'default_context' => ['enable_max_depth' => false]]]]);
        $this->assertTrue($container->hasParameter('.serializer.name_converter'));
        $this->assertSame('serializer.name_converter.camel_case_to_snake_case', $container->getParameter('.serializer.name_converter'));
        $this->assertTrue($container->hasParameter('serializer.default_context'));
        $this->assertSame(['enable_max_depth' => true], $container->getParameter('serializer.default_context'));
        $this->assertTrue($container->hasParameter('.serializer.named_serializers'));
        $this->assertSame(['api' => ['include_built_in_normalizers' => true, 'include_built_in_encoders' => true, 'default_context' => ['enable_max_depth' => false]]], $container->getParameter('.serializer.named_serializers'));
    }


    public function testSerializerCacheActivated()
    {
        $container = $this->load(['enabled' => true]);

        $this->assertTrue($container->hasDefinition('serializer.mapping.cache_class_metadata_factory'));

        $cache = $container->getDefinition('serializer.mapping.cache_class_metadata_factory')->getArgument(1);
        $this->assertEquals(new Reference('serializer.mapping.cache.symfony'), $cache);
    }

    public function testSerializerCacheUsedWithoutAttributesAndMappingFiles()
    {
        $container = $this->load(['enable_attributes' => true], debug: true);
        $this->assertFalse($container->hasDefinition('serializer.mapping.cache_class_metadata_factory'));
    }

    public function testSerializerCacheUsedWithoutAttributesAndMappingFilesNoDebug()
    {
        $container = $this->load(['enable_attributes' => true], debug: false);
        $this->assertTrue($container->hasDefinition('serializer.mapping.cache_class_metadata_factory'));
    }

    public function testSerializerCacheNotActivatedWithAttributes()
    {
        $container = $this->load(['enable_attributes' => true], debug: true);
        $this->assertFalse($container->hasDefinition('serializer.mapping.cache_class_metadata_factory'));
    }

    public function testSerializerServiceIsRegisteredWhenEnabled()
    {
        $container = $this->load(['enabled' => true]);

        $this->assertTrue($container->hasDefinition('serializer'));
    }

    public function testSerializerServiceIsNotRegisteredWhenDisabled()
    {
        $container = $this->load(['enabled' => false]);

        $this->assertFalse($container->hasDefinition('serializer'));
    }

    public function testDataUriNormalizerRegistered()
    {
        $container = $this->load(['enabled' => true]);

        $definition = $container->getDefinition('serializer.normalizer.data_uri');
        $tag = $definition->getTag('serializer.normalizer');

        $this->assertEquals(DataUriNormalizer::class, $definition->getClass());
        $this->assertEquals(-920, $tag[0]['priority']);
    }

    public function testDateIntervalNormalizerRegistered()
    {
        $container = $this->load(['enabled' => true]);

        $definition = $container->getDefinition('serializer.normalizer.dateinterval');
        $tag = $definition->getTag('serializer.normalizer');

        $this->assertEquals(DateIntervalNormalizer::class, $definition->getClass());
        $this->assertEquals(-915, $tag[0]['priority']);
    }

    public function testDateTimeNormalizerRegistered()
    {
        $container = $this->load(['enabled' => true]);

        $definition = $container->getDefinition('serializer.normalizer.datetime');
        $tag = $definition->getTag('serializer.normalizer');

        $this->assertEquals(DateTimeNormalizer::class, $definition->getClass());
        $this->assertEquals(-910, $tag[0]['priority']);
    }

    public function testFormErrorNormalizerRegistred()
    {
        $container = $this->load(['enabled' => true]);

        $definition = $container->getDefinition('serializer.normalizer.form_error');
        $tag = $definition->getTag('serializer.normalizer');

        $this->assertEquals(FormErrorNormalizer::class, $definition->getClass());
        $this->assertEquals(-915, $tag[0]['priority']);
    }

    public function testJsonSerializableNormalizerRegistered()
    {
        $container = $this->load(['enabled' => true]);

        $definition = $container->getDefinition('serializer.normalizer.json_serializable');
        $tag = $definition->getTag('serializer.normalizer');

        $this->assertEquals(JsonSerializableNormalizer::class, $definition->getClass());
        $this->assertEquals(-950, $tag[0]['priority']);
    }

    public function testObjectNormalizerRegistered()
    {
        $container = $this->load([
            'default_context' => ['enable_max_depth' => true],
            'circular_reference_handler' => 'my.circular.reference.handler',
            'max_depth_handler' => 'my.max.depth.handler',
        ], merge: false);
        $container->addCompilerPass(new SerializerPass());
        $container->addCompilerPass(new ResolveBindingsPass());
        $container->compile();

        $definition = $container->getDefinition('serializer.normalizer.object');
        $tag = $definition->getTag('serializer.normalizer');

        $this->assertEquals(ObjectNormalizer::class, $definition->getClass());
        $this->assertEquals(-1000, $tag[0]['priority']);

        $this->assertEquals([
            'enable_max_depth' => true,
            'circular_reference_handler' => new Reference('my.circular.reference.handler'),
            'max_depth_handler' => new Reference('my.max.depth.handler'),
        ], $definition->getArgument(6));
    }

    public function testConstraintViolationListNormalizerRegistered()
    {
        $container = $this->load(['enabled' => true]);

        $definition = $container->getDefinition('serializer.normalizer.constraint_violation_list');
        $tag = $definition->getTag('serializer.normalizer');

        $this->assertEquals(ConstraintViolationListNormalizer::class, $definition->getClass());
        $this->assertEquals(-915, $tag[0]['priority']);
        $this->assertEquals(new Reference('serializer.name_converter.metadata_aware'), $definition->getArgument(1));
    }

    public function testTranslatableNormalizerRegistered()
    {
        $container = $this->load(['enabled' => true]);

        $definition = $container->getDefinition('serializer.normalizer.translatable');
        $tag = $definition->getTag('serializer.normalizer');

        $this->assertSame(TranslatableNormalizer::class, $definition->getClass());
        $this->assertSame(-920, $tag[0]['priority']);
        $this->assertEquals(new Reference('translator'), $definition->getArgument('$translator'));
    }

    public function testBackedEnumNormalizerRegistered()
    {
        $container = $this->load(['enabled' => true]);

        $definition = $container->getDefinition('serializer.normalizer.backed_enum');
        $tag = $definition->getTag('serializer.normalizer');

        $this->assertSame(BackedEnumNormalizer::class, $definition->getClass());
        $this->assertSame(-915, $tag[0]['priority']);
    }

    public function testCachePoolIsRegisteredAlongsideTheSerializer()
    {
        $this->assertTrue($this->load(['enabled' => true])->has('cache.serializer'));
        $this->assertFalse($this->load(['enabled' => true], cache: false)->has('cache.serializer'));
    }

    public function testDataCollectorFollowsTheProfilerInDebugMode()
    {
        $container = $this->load(['enabled' => true], debug: true, profiler: true);
        $this->assertTrue($container->hasDefinition('serializer.data_collector'));
        $this->assertTrue($container->hasDefinition('debug.serializer'));

        $this->assertFalse($this->load(['enabled' => true], debug: true)->hasDefinition('serializer.data_collector'));
        $this->assertFalse($this->load(['enabled' => true], profiler: true)->hasDefinition('serializer.data_collector'));
    }

    public function testJsonDetailedErrorMessagesEnabledInDebugMode()
    {
        $container = $this->load(['default_context' => ['foo' => 'bar']], debug: true);

        $this->assertSame(['foo' => 'bar', JsonDecode::DETAILED_ERROR_MESSAGES => true], $container->getParameter('serializer.default_context'));
    }

    public function testJsonDetailedErrorMessagesCanBeDisabledInDebugMode()
    {
        $container = $this->load(['default_context' => ['foo' => 'bar', JsonDecode::DETAILED_ERROR_MESSAGES => false]], debug: true);

        $this->assertSame(['foo' => 'bar', JsonDecode::DETAILED_ERROR_MESSAGES => false], $container->getParameter('serializer.default_context'));
    }

    public function testJsonDetailedErrorMessagesNotEnabledOutsideDebugMode()
    {
        $container = $this->load(['default_context' => ['foo' => 'bar']]);

        $this->assertSame(['foo' => 'bar'], $container->getParameter('serializer.default_context'));
    }

    public function testNamedSerializersReservedName()
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('Invalid configuration for path "serializer.named_serializers": "default" is a reserved name.');

        $this->load(['named_serializers' => ['default' => ['include_built_in_normalizers' => false]]]);
    }

    /**
     * @param array<string, mixed> $config
     */
    private function load(array $config, bool $debug = false, bool $translator = true, bool $merge = true, bool $cache = true, bool $profiler = false): ContainerBuilder
    {
        $container = new ContainerBuilder(new EnvPlaceholderParameterBag([
            'kernel.debug' => $debug,
            'kernel.build_dir' => sys_get_temp_dir(),
            'kernel.project_dir' => '/app',
            'kernel.bundles_metadata' => [],
            'kernel.container_class' => 'TestContainer',
        ]));

        // what the neighbouring bundles would have registered
        $container->register('property_accessor', \stdClass::class);

        if ($cache) {
            $container->register('cache.system', \stdClass::class);
        }

        if ($profiler) {
            $container->register('profiler', \stdClass::class);
        }

        if ($translator) {
            $container->register('translator', \stdClass::class);
        }

        $container->registerExtension(new SerializerBundle()->getContainerExtension());
        $container->loadFromExtension('serializer', $config);

        if (!$merge) {
            // the caller compiles, which runs MergeExtensionConfigurationPass itself
            $container->addCompilerPass(new ContainerRemoveMissingDependenciesPass());
            $container->addCompilerPass(new RemoveMissingDependenciesPass());
            $container->getCompilerPassConfig()->setRemovingPasses([]);

            return $container;
        }

        new MergeExtensionConfigurationPass()->process($container);
        new ContainerRemoveMissingDependenciesPass()->process($container);
        new RemoveMissingDependenciesPass()->process($container);

        return $container;
    }
}
