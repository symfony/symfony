<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\ObjectMapper\Tests\CacheWarmer;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\NullAdapter;
use Symfony\Component\Cache\Adapter\PhpArrayAdapter;
use Symfony\Component\ObjectMapper\CacheWarmer\ObjectMapperCacheWarmer;
use Symfony\Component\ObjectMapper\Metadata\Mapping;
use Symfony\Component\ObjectMapper\Metadata\ObjectMapperMetadataFactoryInterface;
use Symfony\Component\ObjectMapper\Metadata\ReflectionObjectMapperMetadataFactory;
use Symfony\Component\ObjectMapper\Tests\Fixtures\A;
use Symfony\Component\ObjectMapper\Tests\Fixtures\B;
use Symfony\Component\ObjectMapper\Tests\Fixtures\CacheWarmer\AbstractSource;
use Symfony\Component\ObjectMapper\Tests\Fixtures\CacheWarmer\AbstractSourceView;
use Symfony\Component\ObjectMapper\Tests\Fixtures\CacheWarmer\ClosureTransformSource;
use Symfony\Component\ObjectMapper\Tests\Fixtures\CacheWarmer\ClosureTransformView;

class ObjectMapperCacheWarmerTest extends TestCase
{
    private string $file;
    private ?PhpArrayAdapter $arrayPool = null;

    protected function setUp(): void
    {
        $this->file = sys_get_temp_dir().'/sf_object_mapper_cache_warmer_test/object_mapper.php';
        @mkdir(\dirname($this->file));
        @unlink($this->file);
    }

    protected function tearDown(): void
    {
        $this->arrayPool?->clear();
        $this->arrayPool = null;
        @unlink($this->file);
        @rmdir(\dirname($this->file));
    }

    public function testWarmUpStoresTheClassPropertyNamesAndPropertyMetadataOfEveryMappedPair()
    {
        $warmer = new ObjectMapperCacheWarmer([A::class => [B::class]], $this->file, new ReflectionObjectMapperMetadataFactory());
        $warmer->warmUp(\dirname($this->file), \dirname($this->file));

        $this->assertFileExists($this->file);
        $pool = $this->getArrayPool();
        $this->assertTrue($pool->getItem(self::classMetadataKey(A::class, B::class))->isHit());
        $this->assertTrue($pool->getItem(self::propertyNamesKey(A::class))->isHit());
        $this->assertTrue($pool->getItem(self::propertyNamesKey(B::class))->isHit());
        $this->assertTrue($pool->getItem(self::propertyMetadataKey(A::class, B::class))->isHit());
        $this->assertArrayHasKey('foo', $pool->getItem(self::propertyMetadataKey(A::class, B::class))->get());
    }

    public function testWarmUpSkipsAPairWhoseMetadataCannotBeExportedAndKeepsTheOthers()
    {
        $metadataFactory = new class(new ReflectionObjectMapperMetadataFactory()) implements ObjectMapperMetadataFactoryInterface {
            public function __construct(private readonly ObjectMapperMetadataFactoryInterface $inner)
            {
            }

            public function create(object $object, ?string $property = null, array $context = []): array
            {
                if ($object instanceof ClosureTransformSource && 'name' === $property) {
                    return [new Mapping(transform: static fn (mixed $value): mixed => $value)];
                }

                return $this->inner->create($object, $property, $context);
            }
        };

        $warmer = new ObjectMapperCacheWarmer([
            ClosureTransformSource::class => [ClosureTransformView::class],
            A::class => [B::class],
        ], $this->file, $metadataFactory);
        $warmer->warmUp(\dirname($this->file), \dirname($this->file));

        $pool = $this->getArrayPool();
        $this->assertFalse($pool->getItem(self::classMetadataKey(ClosureTransformSource::class, ClosureTransformView::class))->isHit());
        $this->assertFalse($pool->getItem(self::propertyMetadataKey(ClosureTransformSource::class, ClosureTransformView::class))->isHit());
        $this->assertTrue($pool->getItem(self::propertyMetadataKey(A::class, B::class))->isHit());
    }

    public function testWarmUpSkipsAnAbstractSource()
    {
        $warmer = new ObjectMapperCacheWarmer([
            AbstractSource::class => [AbstractSourceView::class],
            A::class => [B::class],
        ], $this->file, new ReflectionObjectMapperMetadataFactory());
        $warmer->warmUp(\dirname($this->file), \dirname($this->file));

        $pool = $this->getArrayPool();
        $this->assertFalse($pool->getItem(self::classMetadataKey(AbstractSource::class, AbstractSourceView::class))->isHit());
        $this->assertFalse($pool->getItem(self::propertyNamesKey(AbstractSource::class))->isHit());
        $this->assertTrue($pool->getItem(self::classMetadataKey(A::class, B::class))->isHit());
    }

    public function testWarmUpWritesNothingWithoutABuildDir()
    {
        $warmer = new ObjectMapperCacheWarmer([A::class => [B::class]], $this->file, new ReflectionObjectMapperMetadataFactory());

        $this->assertSame([], $warmer->warmUp(\dirname($this->file)));
        $this->assertFileDoesNotExist($this->file);
    }

    private function getArrayPool(): PhpArrayAdapter
    {
        return $this->arrayPool = new PhpArrayAdapter($this->file, new NullAdapter());
    }

    private static function classMetadataKey(string $source, string $target): string
    {
        return 'class_metadata__'.self::encode($source).'__'.self::encode($target);
    }

    private static function propertyNamesKey(string $class): string
    {
        return 'property_names__'.self::encode($class);
    }

    private static function propertyMetadataKey(string $source, string $target): string
    {
        return 'property_metadata__'.self::encode($source).'__'.self::encode($target);
    }

    private static function encode(string $class): string
    {
        return rawurlencode(strtr($class, '\\', '_'));
    }
}
