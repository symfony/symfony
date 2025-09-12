<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\ObjectMapper\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\ObjectMapper\Exception\MappingException;
use Symfony\Component\ObjectMapper\Internal\MappingCacheGenerator;
use Symfony\Component\ObjectMapper\Metadata\Mapping;
use Symfony\Component\ObjectMapper\Metadata\ObjectMapperMetadataFactoryInterface;
use Symfony\Component\ObjectMapper\Metadata\ReflectionObjectMapperMetadataFactory;
use Symfony\Component\ObjectMapper\ObjectMapperInterface;
use Symfony\Component\ObjectMapper\Tests\Fixtures\ClassWithoutTarget;
use Symfony\Component\ObjectMapper\Tests\Fixtures\ServiceLocator\A as ServiceLocatorA;
use Symfony\Component\ObjectMapper\Tests\Fixtures\ServiceLocator\B as ServiceLocatorB;
use Symfony\Component\ObjectMapper\Tests\Fixtures\SimpleSource;
use Symfony\Component\ObjectMapper\Tests\Fixtures\SimpleTarget;

final class MappingCacheGeneratorTest extends TestCase
{
    private static ?string $cacheDir = null;

    public static function getCacheDir(): string
    {
        if (self::$cacheDir) {
            return self::$cacheDir;
        }

        self::$cacheDir = sys_get_temp_dir().'/symfony_object_mapper_cache_test_generator_'.uniqid();
        if (!is_dir(self::$cacheDir)) {
            mkdir(self::$cacheDir, 0o775, true);
        }

        return self::$cacheDir;
    }

    public static function tearDownAfterClass(): void
    {
        if (is_dir(self::$cacheDir)) {
            array_map('unlink', glob(self::$cacheDir.'/*'));
            rmdir(self::$cacheDir);
        }
    }

    public function testGenerateAndWriteCacheFile()
    {
        $metadataFactory = new ReflectionObjectMapperMetadataFactory();
        $generator = new MappingCacheGenerator($metadataFactory);

        $sourceClass = SimpleSource::class;
        $targetClass = SimpleTarget::class;

        $generatedCode = $generator->generate($sourceClass, $targetClass);
        $this->assertIsString($generatedCode);

        $cacheFile = self::getCacheDir().'/'.hash('xxh128', $sourceClass.'-to-'.$targetClass).'.php';
        $generator->write($cacheFile, $generatedCode);

        $this->assertFileExists($cacheFile);

        $mappingFunction = require $cacheFile;
        $this->assertIsCallable($mappingFunction);

        $source = new SimpleSource();
        $source->foo = 'test_value';
        $target = new SimpleTarget();

        $objectMapper = $this->createStub(ObjectMapperInterface::class);
        $objectMap = new \SplObjectStorage();

        $mappedTarget = $mappingFunction(
            $source,
            $target,
            $objectMapper,
            $metadataFactory,
            $objectMap,
            null,
            null,
            null,
            false
        );

        $this->assertInstanceOf(SimpleTarget::class, $mappedTarget);
        $this->assertEquals('test_value', $mappedTarget->bar);

        $expectedFixturePath = __DIR__.'/Fixtures/generated/simple_source_to_simple_target_mapping.php';
        $this->assertStringEqualsFile($expectedFixturePath, $generatedCode);
    }

    public function testClosure()
    {
        $this->expectException(MappingException::class);
        $this->expectExceptionMessage('The transform on "stdClass" can not be exported.');

        $u = new \stdClass();
        $metadata = $this->createStub(ObjectMapperMetadataFactoryInterface::class);
        $metadata->method('create')->with($u)->willReturn([new Mapping(target: ClassWithoutTarget::class, transform: fn () => new \stdClass())]);
        $generator = new MappingCacheGenerator($metadata);
        $generator->generate($u::class, ClassWithoutTarget::class);
    }

    public function testServiceLocatorMappingCacheGeneration()
    {
        $metadataFactory = new ReflectionObjectMapperMetadataFactory();
        $generator = new MappingCacheGenerator($metadataFactory);

        $sourceClass = ServiceLocatorA::class;
        $targetClass = ServiceLocatorB::class;

        $generatedCode = $generator->generate($sourceClass, $targetClass);
        $expectedFixturePath = __DIR__.'/Fixtures/generated/a460713f6773437414a547074965e45e.php';
        $this->assertStringEqualsFile($expectedFixturePath, $generatedCode);
    }

    public function testPromotedConstructorMappingCacheGeneration()
    {
        $metadataFactory = new ReflectionObjectMapperMetadataFactory();
        $generator = new MappingCacheGenerator($metadataFactory);

        $sourceClass = Fixtures\PromotedConstructor\Source::class;
        $targetClass = Fixtures\PromotedConstructor\Target::class;

        $generatedCode = $generator->generate($sourceClass, $targetClass);
        $expectedFixturePath = __DIR__.'/Fixtures/generated/promoted_constructor_mapping.php';
        $this->assertStringEqualsFile($expectedFixturePath, $generatedCode);
    }
}
