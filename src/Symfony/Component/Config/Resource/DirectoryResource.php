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
    private ?string $hash = null;

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

        // mtimes have a one-second resolution: remember the content of the most recently modified entries to tell if they change again in the same second
        // Reproducible builds skip this: which entries are the most recent depends on the checkout
        if (!isset($_SERVER['SOURCE_DATE_EPOCH']) && (!$this->pattern || false !== @preg_match($this->pattern, ''))) {
            try {
                $mtimes = iterator_to_array($this->getMTimes());
            } catch (\UnexpectedValueException) {
                return;
            }

            $this->hash = $this->hashEntries(array_keys($mtimes, max($mtimes), true));
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

        $paths = [];
        foreach ($this->getMTimes() as $path => $mtime) {
            // early return if an entry was modified after the passed timestamp
            if ($timestamp < $mtime) {
                return false;
            }

            if ($timestamp === $mtime) {
                $paths[] = $path;
            }
        }

        // mtimes have a one-second resolution: entries modified in the same second as $timestamp may have changed after being loaded
        return !$paths || (null !== $this->hash && $this->hash === $this->hashEntries($paths));
    }

    public function __serialize(): array
    {
        $data = [
            'resource' => $this->resource,
            'pattern' => $this->pattern,
        ];

        if (null !== $this->hash) {
            $data['hash'] = $this->hash;
        }

        return $data;
    }

    /**
     * @return \Generator<string, int>
     */
    private function getMTimes(): \Generator
    {
        yield $this->resource => filemtime($this->resource);

        // SELF_FIRST yields directories too, so deleted files are detected through their parent's mtime.
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->resource, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::SELF_FIRST) as $file) {
            // if regex filtering is enabled only check matching files
            if ($this->pattern && $file->isFile() && !preg_match($this->pattern, $file->getBasename())) {
                continue;
            }

            // for broken links
            try {
                $mtime = $file->getMTime();
            } catch (\RuntimeException) {
                continue;
            }

            yield $file->getPathname() => $mtime;
        }
    }

    /**
     * Hashes the names of the entries of directories and the content of files.
     *
     * @param list<string> $paths
     */
    private function hashEntries(array $paths): ?string
    {
        sort($paths);
        $hash = hash_init('xxh128');

        foreach ($paths as $path) {
            if (false === $content = is_dir($path) ? @scandir($path) : @hash_file('xxh128', $path)) {
                return null;
            }

            hash_update($hash, $path."\0".(\is_array($content) ? implode("\0", $content) : $content)."\0");
        }

        return hash_final($hash);
    }
}
