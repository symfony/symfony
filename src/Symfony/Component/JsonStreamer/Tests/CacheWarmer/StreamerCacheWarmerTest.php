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
use Psr\Log\NullLogger;
use Symfony\Component\JsonStreamer\CacheWarmer\StreamerCacheWarmer;
use Symfony\Component\JsonStreamer\Mapping\PropertyMetadataLoader;
use Symfony\Component\JsonStreamer\Tests\Fixtures\Model\ClassicDummy;
use Symfony\Component\JsonStreamer\Tests\Fixtures\Model\DummyWithNameAttributes;
use Symfony\Component\JsonStreamer\Tests\ServiceContainer;
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
            \sprintf('%s/0298ebfc1e506bf5ee0c524f00cf5f84.json.php', $this->streamWritersDir),
            \sprintf('%s/4b8ee05a4df30791c5432398e4658476.json.php', $this->streamWritersDir),
            \sprintf('%s/620465ac8aabc3bbf686ee39e0e9f195.json.php', $this->streamWritersDir),
        ], glob($this->streamWritersDir.'/*'));

        $this->assertSame([
            \sprintf('%s/0298ebfc1e506bf5ee0c524f00cf5f84.json.php', $this->streamReadersDir),
            \sprintf('%s/0298ebfc1e506bf5ee0c524f00cf5f84.json.stream.php', $this->streamReadersDir),
            \sprintf('%s/4b8ee05a4df30791c5432398e4658476.json.php', $this->streamReadersDir),
            \sprintf('%s/4b8ee05a4df30791c5432398e4658476.json.stream.php', $this->streamReadersDir),
            \sprintf('%s/620465ac8aabc3bbf686ee39e0e9f195.json.php', $this->streamReadersDir),
            \sprintf('%s/620465ac8aabc3bbf686ee39e0e9f195.json.stream.php', $this->streamReadersDir),
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
            new NullLogger(),
            null,
            new ServiceContainer(),
        );
    }
}
