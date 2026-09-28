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
use Symfony\Component\Lock\LockFactory;

/**
 * @psalm-import-type HarData from HarFile
 */
final class FilesystemStore implements StoreInterface
{
    /**
     * @param string|null $lockDirectory Where lock files are created when no lock factory is given, defaults to the system temporary directory
     */
    public function __construct(
        private ?LockFactory $lockFactory = null,
        private ?string $lockDirectory = null,
    ) {
    }

    public function update(string $name, callable $mutate): void
    {
        if (!self::isAbsolutePath($name)) {
            throw new \InvalidArgumentException(\sprintf('The path "%s" must be absolute.', $name));
        }

        $dir = \dirname($name);

        if (!is_dir($dir) && !@mkdir($dir, 0o777, true) && !is_dir($dir)) {
            throw new \RuntimeException(\sprintf('Unable to create the "%s" directory.', $dir));
        }

        $key = 'sf_har_'.hash('xxh128', $name);

        if ($this->lockFactory) {
            $lock = $this->lockFactory->createLock($key);
            $lock->acquire(true);

            try {
                $this->mutate($name, $mutate);
            } finally {
                $lock->release();
            }

            return;
        }

        // the lock lives outside the fixture directory, so that it never ends up committed next to the records
        $lockDir = $this->lockDirectory ?? sys_get_temp_dir();

        if (!is_dir($lockDir) && !@mkdir($lockDir, 0o777, true) && !is_dir($lockDir)) {
            throw new \RuntimeException(\sprintf('Unable to create the "%s" directory.', $lockDir));
        }

        if (false === $lock = @fopen($lockDir.'/'.$key.'.lock', 'c')) {
            throw new \RuntimeException(\sprintf('Unable to open the lock file for "%s".', $name));
        }

        try {
            flock($lock, \LOCK_EX);

            $this->mutate($name, $mutate);
        } finally {
            flock($lock, \LOCK_UN);
            fclose($lock);
        }
    }

    /**
     * @param callable(HarFile):void $mutate
     */
    private function mutate(string $name, callable $mutate): void
    {
        $har = $this->load($name);
        $mutate($har);
        $this->save($name, $har);
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

    /**
     * @internal
     */
    public static function isAbsolutePath(string $path): bool
    {
        return '' !== $path && ('/' === $path[0] || '\\' === $path[0] || preg_match('#^[a-zA-Z]:[\\\/]#', $path));
    }
}
