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

use Composer\Autoload\ClassLoader;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\ConfigCache;
use Symfony\Component\Config\ConfigCacheFactory;
use Symfony\Component\JsonStreamer\Mapping\PropertyMetadataLoader;
use Symfony\Component\JsonStreamer\Mapping\PropertyMetadataLoaderInterface;
use Symfony\Component\JsonStreamer\StreamerDumper;
use Symfony\Component\JsonStreamer\Tests\Fixtures\Enum\DummyBackedEnum;
use Symfony\Component\JsonStreamer\Tests\Fixtures\Model\ClassicDummy;
use Symfony\Component\JsonStreamer\Tests\Fixtures\Model\DummyWithArray;
use Symfony\Component\JsonStreamer\Tests\Fixtures\Model\DummyWithNameAttributes;
use Symfony\Component\JsonStreamer\Tests\Fixtures\Model\DummyWithOtherDummies;
use Symfony\Component\JsonStreamer\Tests\Fixtures\Model\DummyWithRepeatedOtherDummy;
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

        $dumper = new StreamerDumper($this->createStub(PropertyMetadataLoaderInterface::class), $this->cacheDir, new ConfigCacheFactory(true));
        $dumper->dump(Type::int(), $path, static fn () => 'CONTENT');

        $this->assertFileExists($path);
        $this->assertFileExists($path.'.meta');
        $this->assertFileExists($path.'.meta.json');

        $this->assertStringEqualsFile($path, 'CONTENT');
    }

    public function testSourceEditedDuringGenerationIsRejectedInTheNextProcess()
    {
        $directory = sys_get_temp_dir().'/sf-streamer-capture-'.bin2hex(random_bytes(6));
        mkdir($directory);
        $class = 'StreamerCapturedClass'.bin2hex(random_bytes(6));
        $source = $directory.'/model.php';
        $path = $directory.'/streamer.php';
        $server = $_SERVER;
        unset($_SERVER['SOURCE_DATE_EPOCH']);
        $_SERVER['REQUEST_TIME'] = time() - 10;
        file_put_contents($source, '<?php class '.$class.' { public string $value = "v1"; }');
        touch($source, time() - 100);
        require $source;
        $metadataLoader = $this->createStub(PropertyMetadataLoaderInterface::class);
        $metadataLoader->method('load')->willReturn([]);
        $dumper = new StreamerDumper($metadataLoader, $directory, new ConfigCacheFactory(true));
        try {
            $dumper->dump(Type::object($class), $path, static function () use ($source, $class): string {
                file_put_contents($source, str_replace('v1', 'v2', file_get_contents($source)));
                touch($source, time() - 1);
                clearstatcache();

                return (new $class())->value;
            });
            self::assertStringEqualsFile($path, 'v1');
            foreach (ClassLoader::getRegisteredLoaders() as $vendor => $loader) {
                if ($loader->findFile(ConfigCache::class)) {
                    $autoload = $vendor.'/autoload.php';
                    break;
                }
            }
            $process = proc_open([\PHP_BINARY, '-r', 'require $argv[1]; require $argv[2]; echo (new Symfony\Component\Config\ConfigCache($argv[3], true))->isFresh() ? "fresh" : "stale";', $autoload, $source, $path], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
            $output = stream_get_contents($pipes[1]);
            $error = stream_get_contents($pipes[2]);
            fclose($pipes[1]);
            fclose($pipes[2]);
            self::assertSame(0, proc_close($process), $error);
            self::assertSame('stale', $output);
        } finally {
            $_SERVER = $server;
            foreach (glob($directory.'/*') as $file) {
                unlink($file);
            }
            rmdir($directory);
        }
    }

    public function testDumpWithoutConfigCache()
    {
        $path = $this->cacheDir.'/streamer.php';

        $dumper = new StreamerDumper($this->createStub(PropertyMetadataLoaderInterface::class), $this->cacheDir);
        $dumper->dump(Type::int(), $path, static fn () => 'CONTENT');

        $this->assertFileExists($path);
        $this->assertStringEqualsFile($path, 'CONTENT');
    }

    public function testDumpDoesNotOverwriteExistingFile()
    {
        $path = $this->cacheDir.'/streamer.php';

        mkdir($this->cacheDir, 0o700, true);
        file_put_contents($path, 'EXISTING');

        $generated = false;
        $dumper = new StreamerDumper($this->createStub(PropertyMetadataLoaderInterface::class), $this->cacheDir);
        $dumper->dump(Type::int(), $path, static function () use (&$generated): string {
            $generated = true;

            return 'CONTENT';
        });

        $this->assertFalse($generated);
        $this->assertStringEqualsFile($path, 'EXISTING');
    }

    /**
     * @param list<class-string> $expectedClassNames
     */
    #[DataProvider('getCacheResourcesDataProvider')]
    public function testGetCacheResources(Type $type, array $expectedClassNames)
    {
        $path = $this->cacheDir.'/streamer.php';

        $dumper = new StreamerDumper(new PropertyMetadataLoader(TypeResolver::create()), $this->cacheDir, new ConfigCacheFactory(true));
        $dumper->dump($type, $path, static fn () => 'CONTENT');

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
        yield 'object with repeated object properties' => [
            Type::object(DummyWithRepeatedOtherDummy::class),
            [DummyWithRepeatedOtherDummy::class, ClassicDummy::class],
        ];
        yield 'object with self reference' => [Type::object(SelfReferencingDummy::class), [SelfReferencingDummy::class]];
    }
}
