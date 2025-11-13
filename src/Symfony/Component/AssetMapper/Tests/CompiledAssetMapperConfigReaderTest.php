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

use PHPUnit\Framework\TestCase;
use Symfony\Component\AssetMapper\CompiledAssetMapperConfigReader;
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

    public function testLoadConfig()
    {
        $reader = new CompiledAssetMapperConfigReader($this->writableRoot);
        $this->filesystem->dumpFile($this->writableRoot.'/foo.json', '{"foo": "bar"}');
        $this->assertEquals(['foo' => 'bar'], $reader->loadConfig('foo.json'));
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
