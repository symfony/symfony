<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Config\Resource;

/**
 * DirectoryResource represents a resources stored in a subdirectory tree.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @final
 */
class DirectoryResource implements SelfCheckingResourceInterface
{
    private string $resource;
    private ?int $loadedAt = null;
    /** @var array<string, string>|null Fingerprints of entries modified near the load boundary */
    private ?array $recent = null;

    /**
     * @param string      $resource The file path to the resource
     * @param string|null $pattern  A pattern to restrict monitored files
     *
     * @throws \InvalidArgumentException
     */
    public function __construct(
        string $resource,
        private ?string $pattern = null,
    ) {
        $resolvedResource = realpath($resource) ?: (file_exists($resource) ? $resource : false);

        if (false === $resolvedResource || !is_dir($resolvedResource)) {
            throw new \InvalidArgumentException(\sprintf('The directory "%s" does not exist.', $resource));
        }

        $this->resource = $resolvedResource;

        if (isset($_SERVER['SOURCE_DATE_EPOCH'])) {
            return;
        }

        $failed = false;
        set_error_handler(static function () use (&$failed): bool {
            $failed = true;

            return true;
        }, \E_WARNING);
        try {
            if ($this->pattern && false === preg_match($this->pattern, '')) {
                return;
            }

            $this->loadedAt = time();
            $recent = [];
            foreach ($this->getEntries() as $path => $file) {
                try {
                    $mtime = $file->getMTime();
                } catch (\RuntimeException) {
                    if ($file->isLink()) {
                        continue;
                    }

                    return;
                }
                if ($this->loadedAt - 1 <= $mtime) {
                    if (null === $fingerprint = $this->fingerprint($file)) {
                        return;
                    }
                    $recent[$path] = $fingerprint;
                }
            }
            if (!$failed) {
                ksort($recent);
                $this->recent = $recent;
            }
        } catch (\RuntimeException) {
        } finally {
            restore_error_handler();
        }
    }

    public function __toString(): string
    {
        return hash('xxh128', serialize([$this->resource, $this->pattern]));
    }

    public function getResource(): string
    {
        return $this->resource;
    }

    public function getPattern(): ?string
    {
        return $this->pattern;
    }

    public function isFresh(int $timestamp): bool
    {
        if (!is_dir($this->resource)) {
            return false;
        }

        if (null !== $this->loadedAt || null !== $this->recent) {
            return $this->isCapturedStateFresh($timestamp);
        }

        if ($timestamp <= filemtime($this->resource)) {
            return false;
        }

        // SELF_FIRST yields directories too, so deleted files are detected through their parent's mtime.
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->resource, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::SELF_FIRST) as $file) {
            // if regex filtering is enabled only check matching files
            if ($this->pattern && $file->isFile() && !preg_match($this->pattern, $file->getBasename())) {
                continue;
            }

            // for broken links
            try {
                $fileMTime = $file->getMTime();
            } catch (\RuntimeException) {
                continue;
            }

            // early return if a file was modified in the same second as the passed timestamp or later
            if ($timestamp <= $fileMTime) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return array{resource: string, pattern: string|null, loadedAt?: int, recent?: array<string, string>|null}
     */
    public function __serialize(): array
    {
        $data = [
            'resource' => $this->resource,
            'pattern' => $this->pattern,
        ];
        if (null !== $this->loadedAt) {
            $data['loadedAt'] = $this->loadedAt;
            $data['recent'] = $this->recent;
        }

        return $data;
    }

    /**
     * Enumerates the monitored root and entries, retaining directories even when a filename pattern is set.
     *
     * @return \Traversable<string, \SplFileInfo>
     */
    private function getEntries(): \Traversable
    {
        yield '' => new \SplFileInfo($this->resource);
        $prefixLength = \strlen($this->resource) + 1;
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->resource, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::SELF_FIRST) as $file) {
            if ($this->pattern && $file->isFile() && !preg_match($this->pattern, $file->getBasename())) {
                continue;
            }
            yield substr($file->getPathname(), $prefixLength) => $file;
        }
    }

    /**
     * Checks the load boundary and recent-entry fingerprints against the current directory tree.
     */
    private function isCapturedStateFresh(int $timestamp): bool
    {
        if (null === $this->loadedAt || null === $this->recent) {
            return false;
        }

        $seen = [];
        try {
            foreach ($this->getEntries() as $path => $file) {
                try {
                    $mtime = $file->getMTime();
                } catch (\RuntimeException) {
                    if ($file->isLink()) {
                        continue;
                    }

                    return false;
                }
                if ($timestamp < $mtime || $this->loadedAt < $mtime) {
                    return false;
                }
                if ($this->loadedAt - 1 <= $mtime || isset($this->recent[$path])) {
                    if (!isset($this->recent[$path]) || null === ($fingerprint = $this->fingerprint($file)) || $this->recent[$path] !== $fingerprint) {
                        return false;
                    }
                    $seen[$path] = true;
                } elseif ($timestamp === $mtime) {
                    return false;
                }
            }
        } catch (\RuntimeException) {
            return false;
        }

        return \count($seen) === \count($this->recent);
    }

    /**
     * Fingerprints regular-file bytes, directory entries, or special-file types without opening special files.
     */
    private function fingerprint(\SplFileInfo $file): ?string
    {
        $failed = false;
        set_error_handler(static function () use (&$failed): bool {
            $failed = true;

            return true;
        }, \E_WARNING);
        try {
            if (!$file->isDir()) {
                if (!$file->isFile()) {
                    $type = filetype($file->getPathname());

                    return $failed || false === $type ? null : 'type:'.$type;
                }

                $hash = hash_file('xxh128', $file->getPathname());

                return $failed || false === $hash ? null : 'file:'.$hash;
            }

            if (false === $names = scandir($file->getPathname())) {
                return null;
            }
            $entries = [];
            foreach ($names as $name) {
                if ('.' === $name || '..' === $name) {
                    continue;
                }
                $path = $file->getPathname().'/'.$name;
                if ($this->pattern && is_file($path) && !preg_match($this->pattern, $name)) {
                    continue;
                }
                if (false === $type = filetype($path)) {
                    return null;
                }
                $entries[] = [$name, $type];
            }

            return $failed ? null : 'dir:'.hash('xxh128', serialize($entries));
        } finally {
            restore_error_handler();
        }
    }
}
