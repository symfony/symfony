<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpClient\Recorder\Store;

use Symfony\Component\HttpClient\Har\HarFile;

/**
 * @psalm-import-type HarData from HarFile
 */
final class FilesystemStore implements StoreInterface
{
    public function update(string $name, callable $mutate): void
    {
        if (!self::isAbsolutePath($name)) {
            throw new \InvalidArgumentException(\sprintf('The path "%s" must be absolute.', $name));
        }

        $dir = \dirname($name);

        if (!is_dir($dir) && !@mkdir($dir, 0o777, true) && !is_dir($dir)) {
            throw new \RuntimeException(\sprintf('Unable to create the "%s" directory.', $dir));
        }

        // the lock lives outside the fixture directory, so that it never ends up committed next to the records
        $lockFile = sys_get_temp_dir().'/sf_har_'.hash('xxh128', $name).'.lock';

        if (false === $lock = @fopen($lockFile, 'c')) {
            throw new \RuntimeException(\sprintf('Unable to open the lock file for "%s".', $name));
        }

        try {
            flock($lock, \LOCK_EX);

            $har = $this->load($name);
            $mutate($har);
            $this->save($name, $har);
        } finally {
            flock($lock, \LOCK_UN);
            fclose($lock);
        }
    }

    private function load(string $name): HarFile
    {
        if (!is_file($name)) {
            return HarFile::create();
        }

        /** @psalm-var HarData $har */
        $har = json_decode(file_get_contents($name), true, 512, \JSON_THROW_ON_ERROR);

        return new HarFile($har);
    }

    private function save(string $name, HarFile $har): void
    {
        $tmp = @tempnam(\dirname($name), basename($name).'.');

        if (false === $tmp) {
            throw new \RuntimeException(\sprintf('Unable to create a temporary file next to "%s".', $name));
        }

        try {
            if (false === @file_put_contents($tmp, json_encode($har->toArray(), \JSON_PRETTY_PRINT | \JSON_THROW_ON_ERROR))) {
                throw new \RuntimeException(\sprintf('Unable to write the "%s" file.', $name));
            }

            @chmod($tmp, 0o666 & ~umask());

            if (!@rename($tmp, $name)) {
                throw new \RuntimeException(\sprintf('Unable to write the "%s" file.', $name));
            }
        } finally {
            if (is_file($tmp)) {
                @unlink($tmp);
            }
        }
    }

    private static function isAbsolutePath(string $path): bool
    {
        return '' !== $path && ('/' === $path[0] || '\\' === $path[0] || preg_match('#^[a-zA-Z]:[\\\/]#', $path));
    }
}
