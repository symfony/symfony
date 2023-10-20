<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\AssetMapper;

use Symfony\Component\Filesystem\Path;

/**
 * Reads and writes compiled configuration files for asset mapper.
 */
class CompiledAssetMapperConfigReader
{
    public function __construct(private readonly string $directory)
    {
    }

    public function configExists(string $filename): bool
    {
        return is_file(Path::join($this->directory, $filename));
    }

    public function loadConfig(string $filename): array
    {
        return json_decode(file_get_contents(Path::join($this->directory, $filename)), true, 512, \JSON_THROW_ON_ERROR);
    }

    public function saveConfig(string $filename, array $data): string
    {
        $path = Path::join($this->directory, $filename);
        @mkdir(\dirname($path), 0777, true);
        file_put_contents($path, json_encode($data, \JSON_PRETTY_PRINT | \JSON_THROW_ON_ERROR));

        return $path;
    }

    public function removeConfig(string $filename): void
    {
        $path = Path::join($this->directory, $filename);

        if (is_file($path)) {
            unlink($path);
        }
    }
}
