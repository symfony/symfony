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

use Symfony\Component\Process\Process;

/**
 * Compresses a file using Brotli.
 *
 * @author Kévin Dunglas <kevin@dunglas.dev>
 */
final class BrotliCompressor implements SupportedCompressorInterface
{
    use CompressorTrait;

    private const WRAPPER = 'compress.brotli';
    private const COMMAND = 'brotli';
    private const PHP_EXTENSION = 'brotli';
    private const FILE_EXTENSION = 'br';

    public function __construct(
        ?string $executable = null,
    ) {
        $this->executable = $executable;
    }

    /**
     * @return resource
     */
    private function createStreamContext()
    {
        return stream_context_create(['brotli' => ['level' => BROTLI_COMPRESS_LEVEL_MAX]]);
    }

    private function compressWithBinary(string $path): void
    {
        (new Process([$this->executable, '--best', '--force', "--output=$path.".self::FILE_EXTENSION, '--', $path]))->mustRun();
    }
}
