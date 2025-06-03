<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\AssetMapper\Compressor;

use Psr\Log\LoggerInterface;
use Symfony\Component\Process\Process;

/**
 * Compresses a file using zopfli if possible, or fallback on gzip.
 *
 * @author Kévin Dunglas <kevin@dunglas.dev>
 */
final class GzipCompressor implements SupportedCompressorInterface
{
    use CompressorTrait {
        compress as private baseCompress;
        getUnsupportedReason as private baseGetUnsupportedReason;
    }

    private const WRAPPER = 'compress.zlib';
    private const COMMAND = 'gzip';
    private const PHP_EXTENSION = 'zlib';
    private const FILE_EXTENSION = 'gz';

    public function __construct(
        private readonly ZopfliCompressor $zopfliCompressor = new ZopfliCompressor(),
        ?string $executable = null,
        private ?LoggerInterface $logger = null,
    ) {
        $this->executable = $executable;
    }

    public function compress(string $path): void
    {
        if (null === $reason = $this->zopfliCompressor->getUnsupportedReason()) {
            $this->zopfliCompressor->compress($path);

            return;
        } else {
            $this->logger?->warning($reason);
        }

        $this->baseCompress($path);
    }

    public function getUnsupportedReason(): ?string
    {
        if (null === $this->zopfliCompressor->getUnsupportedReason()) {
            return null;
        }

        return $this->baseGetUnsupportedReason();
    }

    /**
     * @return resource
     */
    private function createStreamContext()
    {
        return stream_context_create(['zlib' => ['level' => 9]]);
    }

    private function compressWithBinary(string $path): void
    {
        (new Process([$this->executable, '--best', '--force', '--keep', '--', $path]))->mustRun();
    }
}
