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
 * Compresses a file using zopfli.
 *
 * @author Kévin Dunglas <kevin@dunglas.dev>
 */
final class ZopfliCompressor implements SupportedCompressorInterface
{
    use CompressorTrait;

    private const WRAPPER = ''; // not supported yet https://github.com/kjdev/php-ext-zopfli/issues/23
    private const COMMAND = 'zopfli';
    private const PHP_EXTENSION = '';
    private const FILE_EXTENSION = 'gz';

    public function __construct(
        ?string $executable = null,
    ) {
        $this->executable = $executable;
    }

    private function compressWithBinary(string $path): void
    {
        (new Process([$this->executable, '--', $path]))->mustRun();
    }

    /**
     * @return resource
     */
    private function createStreamContext()
    {
        throw new \BadMethodCallException('Extension is not supported yet.');
    }
}
