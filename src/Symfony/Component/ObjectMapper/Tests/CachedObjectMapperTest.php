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
use Symfony\Component\ObjectMapper\CachedObjectMapper;
use Symfony\Component\ObjectMapper\Exception\MappingException;
use Symfony\Component\ObjectMapper\Exception\MappingTransformException;
use Symfony\Component\ObjectMapper\Metadata\Mapping;
use Symfony\Component\ObjectMapper\Metadata\ObjectMapperMetadataFactoryInterface;
use Symfony\Component\ObjectMapper\Tests\Fixtures\A;
use Symfony\Component\ObjectMapper\Tests\Fixtures\B;
use Symfony\Component\ObjectMapper\Tests\Fixtures\C;
use Symfony\Component\ObjectMapper\Tests\Fixtures\ClassWithoutTarget;
use Symfony\Component\ObjectMapper\Tests\Fixtures\D;
use Symfony\Component\ObjectMapper\Tests\Fixtures\SimpleSource;
use Symfony\Component\ObjectMapper\Tests\Fixtures\SimpleTarget;

final class CachedObjectMapperTest extends TestCase
{
    private static ?string $cacheDir = null;

    public static function getCacheDir(): string
    {
        if (self::$cacheDir) {
            return self::$cacheDir;
        }

        self::$cacheDir = sys_get_temp_dir().'/symfony_object_mapper_cache_test_'.uniqid();
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

    public function testBasicMapping()
    {
        $mapper = new CachedObjectMapper(self::getCacheDir());

        $d = new D(baz: 'foo', bat: 'bar');
        $c = new C(foo: 'foo', bar: 'bar');
        $a = new A();
        $a->foo = 'test';
        $a->transform = 'test';
        $a->baz = 'me';
        $a->notinb = 'test';
        $a->relation = $c;
        $a->relationNotMapped = $d;

        $expected = new B('test');
        $expected->transform = 'TEST';
        $expected->baz = 'me';
        $expected->nomap = true;
        $expected->concat = 'testme';
        $expected->relation = $d;
        $expected->relationNotMapped = $d;

        $result = $mapper->map($a);

        $this->assertEquals($expected, $result);
        $this->assertInstanceOf(B::class, $result);
    }

    public function testCacheFileIsGenerated()
    {
        $mapper = new CachedObjectMapper(self::getCacheDir());

        $source = new SimpleSource();
        $source->foo = 'test';

        $result = $mapper->map($source);
        $this->assertFileExists(self::getCacheDir().'/'.
            str_replace(['\\', '/'], '_', SimpleSource::class.'-to-'.SimpleTarget::class).'.php');
        $this->assertInstanceOf(SimpleTarget::class, $result);
        $this->assertEquals('test', $result->bar);
    }

    public function testCacheIsReused()
    {
        $mapper = new CachedObjectMapper(self::getCacheDir());

        $source1 = new SimpleSource();
        $source1->foo = 'test1';

        $source2 = new SimpleSource();
        $source2->foo = 'test2';

        $result1 = $mapper->map($source1);
        $cacheFiles = glob(self::getCacheDir().'/*.php');
        $initialCacheTime = filemtime($cacheFiles[0]);

        usleep(1000);

        $result2 = $mapper->map($source2);

        $this->assertSame($initialCacheTime, filemtime($cacheFiles[0]));
        $this->assertEquals('test1', $result1->bar);
        $this->assertEquals('test2', $result2->bar);
    }

    public function testMappingToExistingObject()
    {
        $mapper = new CachedObjectMapper(self::getCacheDir());

        $source = new SimpleSource();
        $source->foo = 'test';

        $existingTarget = new SimpleTarget();
        $existingTarget->bar = 'existing';

        $result = $mapper->map($source, $existingTarget);

        $this->assertSame($existingTarget, $result);
        $this->assertEquals('test', $result->bar);
    }

    public function testMappingWithExplicitTargetClass()
    {
        $mapper = new CachedObjectMapper(self::getCacheDir());

        $source = new SimpleSource();
        $source->foo = 'test';

        $result = $mapper->map($source, SimpleTarget::class);

        $this->assertInstanceOf(SimpleTarget::class, $result);
        $this->assertEquals('test', $result->bar);
    }

    public function testClosure()
    {
        $this->expectException(MappingException::class);
        $this->expectExceptionMessage('The transform on "stdClass" can not be exported.');

        $u = new \stdClass();
        $u->foo = 'bar';

        $metadata = $this->createStub(ObjectMapperMetadataFactoryInterface::class);
        $metadata->method('create')->with($u)->willReturn([new Mapping(target: ClassWithoutTarget::class, transform: fn () => new \stdClass())]);
        $mapper = new CachedObjectMapper(self::getCacheDir(), $metadata);
        $mapper->map($u);
    }

    public function testTransformToWrongValueType()
    {
        $this->expectException(MappingTransformException::class);
        $this->expectExceptionMessage('Cannot map "stdClass" to a non-object target of type "string".');

        $u = new \stdClass();
        $u->foo = 'bar';

        $metadata = $this->createStub(ObjectMapperMetadataFactoryInterface::class);
        $metadata->method('create')->with($u)->willReturn([new Mapping(target: \stdClass::class, transform: [self::class, 'transform'])]);
        $mapper = new CachedObjectMapper(self::getCacheDir(), $metadata);
        $mapper->map($u);
    }

    public static function transform(): string
    {
        return 'str';
    }

    public function testTransformToWrongObject()
    {
        $this->expectException(MappingException::class);
        $this->expectExceptionMessage(\sprintf('Expected the mapped object to be an instance of "%s" but got "stdClass".', ClassWithoutTarget::class));

        $u = new \stdClass();
        $u->foo = 'bar';

        $metadata = $this->createStub(ObjectMapperMetadataFactoryInterface::class);
        $metadata->method('create')->with($u)->willReturn([new Mapping(target: ClassWithoutTarget::class, transform: [self::class, 'getStdClass'])]);
        $mapper = new CachedObjectMapper(self::getCacheDir(), $metadata);
        $mapper->map($u);
    }

    public static function getStdClass(): \stdClass
    {
        return new \stdClass();
    }
}
