<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\AssetMapper\Tests;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use Symfony\Component\AssetMapper\CompiledAssetMapperConfigReader;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Filesystem\Exception\IOException;
use Symfony\Component\Filesystem\Filesystem;

class CompiledAssetMapperConfigReaderTest extends TestCase
{
    private Filesystem $filesystem;
    private string $writableRoot;

    protected function setUp(): void
    {
        $this->filesystem = new Filesystem();
        $this->writableRoot = __DIR__.'/../Fixtures/compiled_asset_mapper_config_reader';
        if (!file_exists(__DIR__.'/../Fixtures/compiled_asset_mapper_config_reader')) {
            $this->filesystem->mkdir($this->writableRoot);
        }
        // realpath to help path comparisons in the tests
        $this->writableRoot = realpath($this->writableRoot);
    }

    protected function tearDown(): void
    {
        $this->filesystem->remove($this->writableRoot);
    }

    public function testConfigExists()
    {
        $reader = new CompiledAssetMapperConfigReader($this->writableRoot);
        $this->assertFalse($reader->configExists('foo.json'));
        $this->filesystem->touch($this->writableRoot.'/foo.json');
        $this->assertTrue($reader->configExists('foo.json'));
    }

    public function testConfigExistsIsIgnoredInDebugMode()
    {
        $this->filesystem->touch($this->writableRoot.'/foo.json');

        $prodReader = new CompiledAssetMapperConfigReader($this->writableRoot, false);
        $this->assertTrue($prodReader->configExists('foo.json'));

        // in debug, compiled config must be ignored so dev computes assets dynamically
        $debugReader = new CompiledAssetMapperConfigReader($this->writableRoot, true);
        $this->assertFalse($debugReader->configExists('foo.json'));
    }

    public function testLoadConfig()
    {
        $reader = new CompiledAssetMapperConfigReader($this->writableRoot);
        $this->filesystem->dumpFile($this->writableRoot.'/foo.json', '{"foo": "bar"}');
        $this->assertEquals(['foo' => 'bar'], $reader->loadConfig('foo.json'));
    }

    public function testLoadConfigKeepsTheDecodedDataInTheCache()
    {
        $cache = new ArrayAdapter(0, false);
        $reader = new CompiledAssetMapperConfigReader($this->writableRoot, false, $cache);
        $mtime = time() - 10;
        $reader->saveConfig('foo.json', ['foo' => 'bar']);
        touch($this->writableRoot.'/foo.json', $mtime);

        $this->assertSame(['foo' => 'bar'], $reader->loadConfig('foo.json'));
        $this->assertSame([['foo' => 'bar']], array_values($cache->getValues()));

        // same size and same modification time: the file is not read again
        $reader->saveConfig('foo.json', ['foo' => 'baz']);
        touch($this->writableRoot.'/foo.json', $mtime);

        $this->assertSame(['foo' => 'bar'], $reader->loadConfig('foo.json'));
    }

    #[TestWith([['foo' => 'baz'], 1])]
    #[TestWith([['foo' => 'bazz'], 0])]
    public function testLoadConfigReadsTheFileAgainWhenItChanges(array $newData, int $mtimeShift)
    {
        $reader = new CompiledAssetMapperConfigReader($this->writableRoot, false, new ArrayAdapter(0, false));
        $mtime = time() - 10;
        $reader->saveConfig('foo.json', ['foo' => 'bar']);
        touch($this->writableRoot.'/foo.json', $mtime);
        $this->assertSame(['foo' => 'bar'], $reader->loadConfig('foo.json'));

        $reader->saveConfig('foo.json', $newData);
        touch($this->writableRoot.'/foo.json', $mtime + $mtimeShift);

        $this->assertSame($newData, $reader->loadConfig('foo.json'));
    }

    #[Group('time-sensitive')]
    public function testLoadConfigDoesNotCacheAFileWrittenDuringTheCurrentSecond()
    {
        $cache = new ArrayAdapter(0, false);
        $reader = new CompiledAssetMapperConfigReader($this->writableRoot, false, $cache);

        // compiling twice within a second gives the same modification time, and here the same size
        foreach (['bar', 'baz'] as $value) {
            $reader->saveConfig('foo.json', ['foo' => $value]);
            touch($this->writableRoot.'/foo.json', time());
            $this->assertSame(['foo' => $value], $reader->loadConfig('foo.json'));
        }
        $this->assertSame([], $cache->getValues());

        sleep(1);

        $this->assertSame(['foo' => 'baz'], $reader->loadConfig('foo.json'));
        $this->assertSame([['foo' => 'baz']], array_values($cache->getValues()));
    }

    public function testLoadConfigFailsOnARemovedFileEvenWhenCached()
    {
        $reader = new CompiledAssetMapperConfigReader($this->writableRoot, false, new ArrayAdapter(0, false));
        $reader->saveConfig('foo.json', ['foo' => 'bar']);
        touch($this->writableRoot.'/foo.json', time() - 10);
        $this->assertSame(['foo' => 'bar'], $reader->loadConfig('foo.json'));

        $reader->removeConfig('foo.json');

        $this->assertFalse($reader->configExists('foo.json'));
        $this->expectException(IOException::class);
        $reader->loadConfig('foo.json');
    }

    public function testSaveConfig()
    {
        $reader = new CompiledAssetMapperConfigReader($this->writableRoot);
        $this->assertEquals($this->writableRoot.\DIRECTORY_SEPARATOR.'foo.json', realpath($reader->saveConfig('foo.json', ['foo' => 'bar'])));
        $this->assertEquals(['foo' => 'bar'], json_decode($this->filesystem->readFile($this->writableRoot.'/foo.json'), true));
    }

    public function testRemoveConfig()
    {
        $reader = new CompiledAssetMapperConfigReader($this->writableRoot);
        $this->filesystem->touch($this->writableRoot.'/foo.json');
        $this->assertTrue($reader->configExists('foo.json'));
        $reader->removeConfig('foo.json');
        $this->assertFalse($reader->configExists('foo.json'));
    }
}
