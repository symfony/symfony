<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\JsonStreamer\Tests\CacheWarmer;

use PHPUnit\Framework\TestCase;
use Symfony\Component\JsonStreamer\CacheWarmer\StreamerCacheWarmer;
use Symfony\Component\JsonStreamer\Mapping\PropertyMetadataLoader;
use Symfony\Component\JsonStreamer\Tests\Fixtures\Model\ClassicDummy;
use Symfony\Component\JsonStreamer\Tests\Fixtures\Model\DummyWithNameAttributes;
use Symfony\Component\TypeInfo\TypeResolver\TypeResolver;

class StreamerCacheWarmerTest extends TestCase
{
    private string $streamWritersDir;
    private string $streamReadersDir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->streamWritersDir = \sprintf('%s/symfony_json_streamer_test/stream_writer', sys_get_temp_dir());
        $this->streamReadersDir = \sprintf('%s/symfony_json_streamer_test/stream_reader', sys_get_temp_dir());

        if (is_dir($this->streamWritersDir)) {
            array_map('unlink', glob($this->streamWritersDir.'/*'));
            rmdir($this->streamWritersDir);
        }

        if (is_dir($this->streamReadersDir)) {
            array_map('unlink', glob($this->streamReadersDir.'/*'));
            rmdir($this->streamReadersDir);
        }
    }

    public function testWarmUp()
    {
        $this->cacheWarmer([
            ClassicDummy::class => ['object' => true, 'list' => true],
            DummyWithNameAttributes::class => ['object' => true, 'list' => false],
        ])->warmUp('useless');

        $this->assertSame([
            \sprintf('%s/13791ba3dc4369dc488ec78466326979.json.php', $this->streamWritersDir),
            \sprintf('%s/3d6bea319060b50305c349746ac6cabc.json.php', $this->streamWritersDir),
            \sprintf('%s/6f7c0ed338bb3b8730cc67686a91941b.json.php', $this->streamWritersDir),
        ], glob($this->streamWritersDir.'/*'));

        $this->assertSame([
            \sprintf('%s/13791ba3dc4369dc488ec78466326979.json.php', $this->streamReadersDir),
            \sprintf('%s/13791ba3dc4369dc488ec78466326979.json.stream.php', $this->streamReadersDir),
            \sprintf('%s/3d6bea319060b50305c349746ac6cabc.json.php', $this->streamReadersDir),
            \sprintf('%s/3d6bea319060b50305c349746ac6cabc.json.stream.php', $this->streamReadersDir),
            \sprintf('%s/6f7c0ed338bb3b8730cc67686a91941b.json.php', $this->streamReadersDir),
            \sprintf('%s/6f7c0ed338bb3b8730cc67686a91941b.json.stream.php', $this->streamReadersDir),
        ], glob($this->streamReadersDir.'/*'));
    }

    /**
     * @param array<class-string, array{object: bool, list: bool}> $streamable
     */
    private function cacheWarmer(array $streamable = []): StreamerCacheWarmer
    {
        $typeResolver = TypeResolver::create();

        return new StreamerCacheWarmer(
            $streamable,
            new PropertyMetadataLoader($typeResolver),
            new PropertyMetadataLoader($typeResolver),
            $this->streamWritersDir,
            $this->streamReadersDir,
        );
    }
}
