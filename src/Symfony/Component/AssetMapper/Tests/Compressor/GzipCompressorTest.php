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
use Psr\Log\LoggerInterface;
use Symfony\Component\AssetMapper\Compressor\GzipCompressor;
use Symfony\Component\AssetMapper\Compressor\ZopfliCompressor;
use Symfony\Component\Filesystem\Filesystem;

/**
 * @author Kévin Dunglas <kevin@dunglas.dev>
 */
class GzipCompressorTest extends TestCase
{
    private const WRITABLE_ROOT = __DIR__.'/../Fixtures/gzip_compressor_filesystem';

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
        $this->filesystem->dumpFile(self::WRITABLE_ROOT.'/foo/bar.js', 'foobar');

        (new GzipCompressor())->compress(self::WRITABLE_ROOT.'/foo/bar.js');

        $this->assertFileExists(self::WRITABLE_ROOT.'/foo/bar.js.gz');
    }

    public function testCompressFallsBackWhenZopfliIsUnsupported()
    {
        $zopfli = new ZopfliCompressor();
        if (null === $reason = $zopfli->getUnsupportedReason()) {
            $this->markTestSkipped('Zopfli is available; cannot exercise the fallback path.');
        }

        $this->filesystem->dumpFile(self::WRITABLE_ROOT.'/foo/bar.js', 'foobar');

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('warning')->with($reason);

        (new GzipCompressor($zopfli, null, $logger))->compress(self::WRITABLE_ROOT.'/foo/bar.js');

        $this->assertFileExists(self::WRITABLE_ROOT.'/foo/bar.js.gz');
    }
}
