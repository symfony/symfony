<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\AssetMapper\Tests\Compressor;

use PHPUnit\Framework\TestCase;
use Symfony\Component\AssetMapper\Compressor\BrotliCompressor;
use Symfony\Component\AssetMapper\Compressor\ChainCompressor;
use Symfony\Component\AssetMapper\Compressor\GzipCompressor;
use Symfony\Component\AssetMapper\Compressor\ZstandardCompressor;
use Symfony\Component\Filesystem\Filesystem;

/**
 * @author Kévin Dunglas <kevin@dunglas.dev>
 */
class ChainCompressorTest extends TestCase
{
    private const WRITABLE_ROOT = __DIR__.'/../Fixtures/chain_compressor_filesystem';

    private Filesystem $filesystem;

    protected function setUp(): void
    {
        $this->filesystem = new Filesystem();
        if (!file_exists(self::WRITABLE_ROOT)) {
            $this->filesystem->mkdir(self::WRITABLE_ROOT);
        }
    }

    protected function tearDown(): void
    {
        $this->filesystem->remove(self::WRITABLE_ROOT);
    }

    public function testCompress()
    {
        $extensions = [];
        if (null === (new GzipCompressor())->getUnsupportedReason()) {
            $extensions[] = 'gz';
        }
        if (null === (new BrotliCompressor())->getUnsupportedReason()) {
            $extensions[] = 'br';
        }
        if (null === (new ZstandardCompressor())->getUnsupportedReason()) {
            $extensions[] = 'zst';
        }

        if (!$extensions) {
            $this->markTestSkipped('No supported compressors available.');
        }

        $this->filesystem->dumpFile(self::WRITABLE_ROOT.'/foo/bar.js', 'foobar');

        (new ChainCompressor())->compress(self::WRITABLE_ROOT.'/foo/bar.js');

        foreach ($extensions as $extension) {
            $this->assertFileExists(self::WRITABLE_ROOT.'/foo/bar.js.'.$extension);
        }
    }
}
