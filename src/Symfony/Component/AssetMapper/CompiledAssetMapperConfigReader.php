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

use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;

/**
 * Reads and writes compiled configuration files for asset mapper.
 */
class CompiledAssetMapperConfigReader
{
    private readonly Filesystem $filesystem;

    public function __construct(
        private readonly string $directory,
        private readonly bool $debug = false,
        private readonly ?CacheItemPoolInterface $cache = null,
    ) {
        $this->filesystem = new Filesystem();
    }

    public function configExists(string $filename): bool
    {
        // In debug mode, the compiled config is ignored so the dev environment always
        // recomputes assets from source instead of reading metadata that another
        // environment (e.g. a prior "asset-map:compile" run) may have left behind.
        if ($this->debug) {
            return false;
        }

        return is_file(Path::join($this->directory, $filename));
    }

    public function loadConfig(string $filename): array
    {
        $path = Path::join($this->directory, $filename);

        // modification times are in seconds: a file written during the current second could be written again without changing its key
        if (!$this->cache || false === ($mtime = @filemtime($path)) || $mtime >= time()) {
            return $this->decode($path);
        }

        $item = $this->cache->getItem(hash('xxh128', $path).'.'.filesize($path).'.'.$mtime);

        if (!$item->isHit()) {
            $this->cache->save($item->set($this->decode($path)));
        }

        return $item->get();
    }

    public function saveConfig(string $filename, array $data): string
    {
        $path = Path::join($this->directory, $filename);
        $this->filesystem->dumpFile($path, json_encode($data, \JSON_PRETTY_PRINT | \JSON_THROW_ON_ERROR));

        return $path;
    }

    public function removeConfig(string $filename): void
    {
        $path = Path::join($this->directory, $filename);

        if (is_file($path)) {
            $this->filesystem->remove($path);
        }
    }

    private function decode(string $path): array
    {
        return json_decode($this->filesystem->readFile($path), true, 512, \JSON_THROW_ON_ERROR);
    }
}
