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

use Symfony\Component\Filesystem\Filesystem;

class LocalPublicAssetsFilesystem implements PublicAssetsFilesystemInterface
{
    private Filesystem $filesystem;

    public function __construct(private readonly string $publicDir)
    {
        $this->filesystem = new Filesystem();
    }

    public function write(string $path, string $contents): void
    {
        $targetPath = $this->publicDir.'/'.ltrim($path, '/');

        $this->filesystem->dumpFile($targetPath, $contents);
    }

    public function copy(string $originPath, string $path): void
    {
        $targetPath = $this->publicDir.'/'.ltrim($path, '/');

        $this->filesystem->copy($originPath, $targetPath, true);
    }

    public function getDestinationPath(): string
    {
        return $this->publicDir;
    }
}
