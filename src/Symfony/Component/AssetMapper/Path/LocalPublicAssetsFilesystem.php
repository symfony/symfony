<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\AssetMapper\Path;

use Symfony\Component\AssetMapper\Compressor\CompressorInterface;
use Symfony\Component\Filesystem\Filesystem;

class LocalPublicAssetsFilesystem implements PublicAssetsFilesystemInterface
{
    private Filesystem $filesystem;

    /**
     * @param string[] $extensionsToCompress
     */
    public function __construct(
        private readonly string $publicDir,
        private readonly ?CompressorInterface $compressor = null,
        private readonly array $extensionsToCompress = [],
    ) {
        $this->filesystem = new Filesystem();
    }

    public function write(string $path, string $contents): void
    {
        $targetPath = $this->publicDir.'/'.ltrim($path, '/');

        $this->filesystem->dumpFile($targetPath, $contents);
        $this->compress($targetPath);
    }

    public function copy(string $originPath, string $path): void
    {
        $targetPath = $this->publicDir.'/'.ltrim($path, '/');

        $this->filesystem->copy($originPath, $targetPath, true);
        $this->compress($targetPath);
    }

    public function getDestinationPath(): string
    {
        return $this->publicDir;
    }

    private function compress(string $targetPath): void
    {
        foreach ($this->extensionsToCompress as $ext) {
            if (!str_ends_with($targetPath, ".$ext")) {
                continue;
            }

            $this->compressor?->compress($targetPath);

            return;
        }
    }
}
