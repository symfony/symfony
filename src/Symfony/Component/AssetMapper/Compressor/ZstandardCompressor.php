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
 * Compresses a file using Zstandard.
 *
 * @author Kévin Dunglas <kevin@dunglas.dev>
 */
final class ZstandardCompressor implements SupportedCompressorInterface
{
    use CompressorTrait;

    private const WRAPPER = 'compress.zstd';
    private const COMMAND = 'zstd';
    private const PHP_EXTENSION = 'zstd';
    private const FILE_EXTENSION = 'zst';

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
        return stream_context_create(['zstd' => ['level' => ZSTD_COMPRESS_LEVEL_MAX]]);
    }

    private function compressWithBinary(string $path): void
    {
        (new Process([$this->executable, '-19', '--force', '-o', "$path.".self::FILE_EXTENSION, '--', $path]))->mustRun();
    }
}
