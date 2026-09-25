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

use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\Glob;

/**
 * GlobResource represents a set of resources stored on the filesystem.
 *
 * Only existence/removal is tracked (not mtimes.)
 *
 * @author Nicolas Grekas <p@tchwork.com>
 *
 * @final
 *
 * @implements \IteratorAggregate<string, \SplFileInfo>
 */
class GlobResource implements \IteratorAggregate, SelfCheckingResourceInterface
{
    private string $prefix;
    private string $hash;
    private array $excludedPrefixes;
    private int $globBrace;
    private ?array $directories;

    /**
     * @param string $prefix    A directory prefix
     * @param string $pattern   A glob pattern
     * @param bool   $recursive Whether directories should be scanned recursively or not
     *
     * @throws \InvalidArgumentException
     */
    public function __construct(
        string $prefix,
        private string $pattern,
        private bool $recursive,
        private bool $forExclusion = false,
        array $excludedPrefixes = [],
    ) {
        ksort($excludedPrefixes);
        $resolvedPrefix = realpath($prefix) ?: (file_exists($prefix) ? $prefix : false);
        $this->excludedPrefixes = $excludedPrefixes;
        $this->globBrace = \defined('GLOB_BRACE') ? \GLOB_BRACE : 0;

        if (false === $resolvedPrefix) {
            throw new \InvalidArgumentException(\sprintf('The path "%s" does not exist.', $prefix));
        }

        $this->prefix = $resolvedPrefix;
    }

    public function getPrefix(): string
    {
        return $this->prefix;
    }

    public function __toString(): string
    {
        return 'glob.'.$this->prefix.(int) $this->recursive.$this->pattern.(int) $this->forExclusion.implode("\0", $this->excludedPrefixes);
    }

    public function isFresh(int $timestamp): bool
    {
        if (isset($this->directories) && $this->areDirectoriesUnchanged()) {
            return true;
        }

        $hash = $this->computeHash();
        $this->hash ??= $hash;

        return $this->hash === $hash;
    }

    public function __serialize(): array
    {
        if (!isset($this->hash)) {
            $this->directories = $this->snapshotDirectories();
            $this->hash = $this->computeHash();
        }

        return [
            'prefix' => $this->prefix,
            'pattern' => $this->pattern,
            'recursive' => $this->recursive,
            'hash' => $this->hash,
            'forExclusion' => $this->forExclusion,
            'excludedPrefixes' => $this->excludedPrefixes,
            'directories' => $this->directories ?? null,
        ];
    }

    public function __unserialize(array $data): void
    {
        foreach ($data as $value) {
            if ($value instanceof \Stringable) {
                throw new \BadMethodCallException('Cannot unserialize '.self::class);
            }
        }

        $this->prefix = array_shift($data);
        $this->pattern = array_shift($data);
        $this->recursive = array_shift($data);
        $this->hash = array_shift($data);
        $this->forExclusion = array_shift($data);
        $this->excludedPrefixes = array_shift($data);
        $this->directories = array_shift($data);
        $this->globBrace = \defined('GLOB_BRACE') ? \GLOB_BRACE : 0;
    }

    public function getIterator(): \Traversable
    {
        if ((!$this->recursive && '' === $this->pattern) || !file_exists($this->prefix)) {
            return;
        }

        if (is_file($prefix = str_replace('\\', '/', $this->prefix))) {
            $prefix = \dirname($prefix);
            $pattern = basename($prefix).$this->pattern;
        } else {
            $pattern = $this->pattern;
        }

        if (class_exists(Finder::class)) {
            $regex = Glob::toRegex($pattern);
            if ($this->recursive) {
                $regex = substr_replace($regex, str_ends_with($pattern, '/') ? '' : '(/|$)', -2, 1);
            }
        } else {
            $regex = null;
        }

        $prefixLen = \strlen($prefix);
        $paths = null;

        if ('' === $this->pattern && is_file($this->prefix)) {
            $paths = [$this->prefix => null];
        } elseif (!str_starts_with($this->prefix, 'phar://') && (null !== $regex || !str_contains($this->pattern, '/**/'))) {
            if (!str_contains($this->pattern, '/**/') && ($this->globBrace || !str_contains($this->pattern, '{'))) {
                $paths = array_fill_keys(glob($this->prefix.$this->pattern, \GLOB_NOSORT | $this->globBrace), null);
            } elseif (!str_contains($this->pattern, '\\') || !preg_match('/\\\\[,{}]/', $this->pattern)) {
                $paths = [];
                foreach ($this->expandGlob($this->pattern) as $p) {
                    if (false !== $i = strpos($p, '/**/')) {
                        $p = substr_replace($p, '/*', $i);
                    }
                    $paths += array_fill_keys(glob($this->prefix.$p, \GLOB_NOSORT), false !== $i ? $regex : null);
                }
            }
        }

        if (null !== $paths) {
            uksort($paths, 'strnatcmp');
            foreach ($paths as $path => $regex) {
                if ($this->excludedPrefixes) {
                    $normalizedPath = str_replace('\\', '/', $path);
                    do {
                        if (isset($this->excludedPrefixes[$dirPath = $normalizedPath])) {
                            continue 2;
                        }
                    } while ($prefix !== $dirPath && $dirPath !== $normalizedPath = \dirname($dirPath));
                }

                if ((null === $regex || preg_match($regex, substr(str_replace('\\', '/', $path), $prefixLen))) && is_file($path)) {
                    yield $path => new \SplFileInfo($path);
                }
                if (!is_dir($path)) {
                    continue;
                }
                if ($this->forExclusion && (null === $regex || preg_match($regex, substr(str_replace('\\', '/', $path), $prefixLen)))) {
                    yield $path => new \SplFileInfo($path);
                    continue;
                }
                if (!($this->recursive || null !== $regex) || isset($this->excludedPrefixes[str_replace('\\', '/', $path)])) {
                    continue;
                }
                $files = iterator_to_array(new \RecursiveIteratorIterator(
                    new \RecursiveCallbackFilterIterator(
                        new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS | \FilesystemIterator::FOLLOW_SYMLINKS),
                        fn (\SplFileInfo $file, $path) => !isset($this->excludedPrefixes[$path = str_replace('\\', '/', $path)])
                            && (null === $regex || preg_match($regex, substr($path, $prefixLen)) || $file->isDir())
                            && '.' !== $file->getBasename()[0]
                    ),
                    \RecursiveIteratorIterator::LEAVES_ONLY
                ));
                uksort($files, 'strnatcmp');

                foreach ($files as $path => $info) {
                    if ($info->isFile()) {
                        yield $path => $info;
                    }
                }
            }

            return;
        }

        if (!class_exists(Finder::class)) {
            throw new \LogicException('Extended glob patterns cannot be used as the Finder component is not installed. Try running "composer require symfony/finder".');
        }

        yield from (new Finder())
            ->followLinks()
            ->filter(function (\SplFileInfo $info) use ($regex, $prefixLen, $prefix) {
                $normalizedPath = str_replace('\\', '/', $info->getPathname());
                if (!preg_match($regex, substr($normalizedPath, $prefixLen)) || !$info->isFile()) {
                    return false;
                }
                if ($this->excludedPrefixes) {
                    do {
                        if (isset($this->excludedPrefixes[$dirPath = $normalizedPath])) {
                            return false;
                        }
                    } while ($prefix !== $dirPath && $dirPath !== $normalizedPath = \dirname($dirPath));
                }
            })
            ->sortByName()
            ->in($prefix)
        ;
    }

    private function computeHash(): string
    {
        $hash = hash_init('xxh128');

        foreach ($this->getIterator() as $path => $info) {
            hash_update($hash, $path."\n");
        }

        return hash_final($hash);
    }

    private function expandGlob(string $pattern): array
    {
        $segments = preg_split('/\{([^{}]*+)\}/', $pattern, -1, \PREG_SPLIT_DELIM_CAPTURE);
        $paths = [$segments[0]];
        $patterns = [];

        for ($i = 1; $i < \count($segments); $i += 2) {
            $patterns = [];

            foreach (explode(',', $segments[$i]) as $s) {
                foreach ($paths as $p) {
                    $patterns[] = $p.$s.$segments[1 + $i];
                }
            }

            $paths = $patterns;
        }

        $j = 0;
        foreach ($patterns as $i => $p) {
            if (str_contains($p, '{')) {
                $p = $this->expandGlob($p);
                array_splice($paths, $i + $j, 1, $p);
                $j += \count($p) - 1;
            }
        }

        return $paths;
    }

    /**
     * Records the mtime, the inode and the entries of the directories that decide which paths match.
     *
     * A directory mtime changes when an entry is added, removed or renamed in it, and its inode changes when another directory replaces it, so unchanged mtimes and inodes mean unchanged matching paths.
     * When they did change, e.g. because a file was saved by renaming a temporary file, comparing the entries of that directory is enough.
     *
     * @return array<string, array{int|null, int, string}>|null Keyed by path relative to the prefix, or null when the matching paths can change without any directory changing, e.g. because of a symlink
     */
    private function snapshotDirectories(): ?array
    {
        if (!$this->recursive && '' === $this->pattern) {
            return [];
        }

        if (str_contains($this->prefix, '://') || strpbrk($this->prefix, '*?[{') || !is_dir($this->prefix)
            || ('' !== $this->pattern && '/' !== $this->pattern[0]) || str_contains($this->pattern, '\\')
        ) {
            return null;
        }

        // Only mtimes older than the second before the scan are trusted: a directory can change again within the same second, and filesystem timestamps can lag behind time()
        $time = time() - 1;
        $directories = $children = $scanned = [];

        foreach ($this->expandGlob($this->pattern) as $pattern) {
            if (false !== $i = strpos($pattern, '/**/')) {
                $pattern = substr_replace($pattern, '/*', $i);
            }

            if (str_contains($pattern, '/.') || strpbrk($pattern, '{}')) {
                return null;
            }

            $matches = [''];
            foreach (explode('/', $pattern) as $segment) {
                if ('' === $segment) {
                    continue;
                }

                $parents = $matches;
                $matches = [];
                foreach ($parents as $dir) {
                    if (null === $children[$dir] ??= $this->snapshotDirectory($dir, $directories, $time)) {
                        return null;
                    }

                    foreach ($children[$dir] as $name) {
                        // Case-insensitive filesystems can match more than fnmatch() does
                        if (fnmatch($segment, $name) || fnmatch(strtolower($segment), strtolower($name))) {
                            $matches[] = $dir.'/'.$name;
                        }
                    }
                }
            }

            while (null !== $dir = array_pop($matches)) {
                if (isset($scanned[$dir])) {
                    continue;
                }
                $scanned[$dir] = true;

                if (null === $children[$dir] ??= $this->snapshotDirectory($dir, $directories, $time)) {
                    return null;
                }

                foreach ($children[$dir] as $name) {
                    if (!isset($this->excludedPrefixes[str_replace('\\', '/', $this->prefix.$dir.'/'.$name)])) {
                        $matches[] = $dir.'/'.$name;
                    }
                }
            }
        }

        return $directories;
    }

    private function snapshotDirectory(string $dir, array &$directories, int $time): ?array
    {
        if (false === $mtime = @filemtime($this->prefix.$dir)) {
            return null;
        }

        $inode = fileinode($this->prefix.$dir);
        $hash = self::hashDirectory($this->prefix.$dir, $subdirectories);
        $directories[$dir] = [$mtime < $time ? $mtime : null, $inode, $hash];

        return $subdirectories;
    }

    private function areDirectoriesUnchanged(): bool
    {
        foreach ($this->directories as $dir => [$mtime, $inode, $hash]) {
            if ((null === $mtime || @filemtime($this->prefix.$dir) !== $mtime || fileinode($this->prefix.$dir) !== $inode) && self::hashDirectory($this->prefix.$dir) !== $hash) {
                return false;
            }
        }

        return true;
    }

    /**
     * Hashes the names and types of the entries of a directory, ignoring hidden ones.
     *
     * @param-out list<string>|null $subdirectories The names of the subdirectories, or null when an entry is a symlink
     */
    private static function hashDirectory(string $dir, ?array &$subdirectories = null): ?string
    {
        $subdirectories = null;

        if (false === $names = @scandir($dir)) {
            return null;
        }

        $hash = hash_init('xxh128');
        $subdirectories = [];
        $hasSymlink = false;

        foreach ($names as $name) {
            if ('.' === $name[0]) {
                continue;
            }

            $type = @filetype($dir.'/'.$name);
            hash_update($hash, $name."\0".$type."\0");

            if ('dir' === $type) {
                $subdirectories[] = $name;
            } elseif ('link' === $type) {
                $hasSymlink = true;
            }
        }

        if ($hasSymlink) {
            $subdirectories = null;
        }

        return hash_final($hash);
    }
}
