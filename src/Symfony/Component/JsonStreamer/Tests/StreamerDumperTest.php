<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\JsonStreamer\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\ConfigCacheFactory;
use Symfony\Component\JsonStreamer\Mapping\PropertyMetadataLoader;
use Symfony\Component\JsonStreamer\Mapping\PropertyMetadataLoaderInterface;
use Symfony\Component\JsonStreamer\StreamerDumper;
use Symfony\Component\JsonStreamer\Tests\Fixtures\Enum\DummyBackedEnum;
use Symfony\Component\JsonStreamer\Tests\Fixtures\Model\ClassicDummy;
use Symfony\Component\JsonStreamer\Tests\Fixtures\Model\DummyWithArray;
use Symfony\Component\JsonStreamer\Tests\Fixtures\Model\DummyWithNameAttributes;
use Symfony\Component\JsonStreamer\Tests\Fixtures\Model\DummyWithOtherDummies;
use Symfony\Component\JsonStreamer\Tests\Fixtures\Model\SelfReferencingDummy;
use Symfony\Component\TypeInfo\Type;
use Symfony\Component\TypeInfo\TypeResolver\TypeResolver;

class StreamerDumperTest extends TestCase
{
    private string $cacheDir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cacheDir = \sprintf('%s/symfony_json_streamer_test/any', sys_get_temp_dir());

        if (is_dir($this->cacheDir)) {
            array_map('unlink', glob($this->cacheDir.'/*'));
            rmdir($this->cacheDir);
        }
    }

    public function testDumpWithConfigCache()
    {
        $path = $this->cacheDir.'/streamer.php';

        $dumper = new StreamerDumper($this->createMock(PropertyMetadataLoaderInterface::class), $this->cacheDir, new ConfigCacheFactory(true));
        $dumper->dump(Type::int(), $path, fn () => 'CONTENT');

        $this->assertFileExists($path);
        $this->assertFileExists($path.'.meta');
        $this->assertFileExists($path.'.meta.json');

        $this->assertStringEqualsFile($path, 'CONTENT');
    }

    public function testDumpWithoutConfigCache()
    {
        $path = $this->cacheDir.'/streamer.php';

        $dumper = new StreamerDumper($this->createMock(PropertyMetadataLoaderInterface::class), $this->cacheDir);
        $dumper->dump(Type::int(), $path, fn () => 'CONTENT');

        $this->assertFileExists($path);
        $this->assertStringEqualsFile($path, 'CONTENT');
    }

    /**
     * @param list<class-string> $expectedClassNames
     */
    #[DataProvider('getCacheResourcesDataProvider')]
    public function testGetCacheResources(Type $type, array $expectedClassNames)
    {
        $path = $this->cacheDir.'/streamer.php';

        $dumper = new StreamerDumper(new PropertyMetadataLoader(TypeResolver::create()), $this->cacheDir, new ConfigCacheFactory(true));
        $dumper->dump($type, $path, fn () => 'CONTENT');

        $resources = json_decode(file_get_contents($path.'.meta.json'), true)['resources'];
        $classNames = array_column($resources, 'className');

        $this->assertSame($expectedClassNames, $classNames);
    }

    /**
     * @return iterable<array{0: Type, 1: list<class-string>}>
     */
    public static function getCacheResourcesDataProvider(): iterable
    {
        yield 'scalar' => [Type::int(), []];
        yield 'enum' => [Type::enum(DummyBackedEnum::class), [DummyBackedEnum::class]];
        yield 'object' => [Type::object(ClassicDummy::class), [ClassicDummy::class]];
        yield 'collection of objects' => [
            Type::list(Type::object(ClassicDummy::class)),
            [ClassicDummy::class],
        ];
        yield 'generic with objects' => [
            Type::generic(Type::object(ClassicDummy::class), Type::object(DummyWithArray::class)),
            [DummyWithArray::class, ClassicDummy::class],
        ];
        yield 'union with objects' => [
            Type::union(Type::int(), Type::object(ClassicDummy::class), Type::object(DummyWithArray::class)),
            [ClassicDummy::class, DummyWithArray::class],
        ];
        yield 'intersection with objects' => [
            Type::intersection(Type::object(ClassicDummy::class), Type::object(DummyWithArray::class)),
            [ClassicDummy::class, DummyWithArray::class],
        ];
        yield 'object with object properties' => [
            Type::object(DummyWithOtherDummies::class),
            [DummyWithOtherDummies::class, DummyWithNameAttributes::class, ClassicDummy::class],
        ];
        yield 'object with self reference' => [Type::object(SelfReferencingDummy::class), [SelfReferencingDummy::class]];
    }
}
