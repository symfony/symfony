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

/**
 * Calls multiple compressors in a chain.
 *
 * @author Kévin Dunglas <kevin@dunglas.dev>
 */
final class ChainCompressor implements CompressorInterface
{
    /**
     * @param CompressorInterface[] $compressors
     */
    public function __construct(
        private ?array $compressors = null,
        private readonly ?LoggerInterface $logger = null,
    ) {
    }

    public function compress(string $path): void
    {
        if (null === $this->compressors) {
            $this->compressors = [];
            foreach ([new BrotliCompressor(), new ZstandardCompressor(), new GzipCompressor()] as $compressor) {
                $unsupportedReason = $compressor->getUnsupportedReason();
                if (null === $unsupportedReason) {
                    $this->compressors[] = $compressor;
                } else {
                    $this->logger?->warning($unsupportedReason);
                }
            }
        }

        foreach ($this->compressors as $compressor) {
            $compressor->compress($path);
        }
    }
}
