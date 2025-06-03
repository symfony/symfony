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
        $hash = $this->computeHash();
        $this->hash ??= $hash;

        return $this->hash === $hash;
    }

    /**
     * @internal
     */
    public function __sleep(): array
    {
        $this->hash ??= $this->computeHash();

        return ['prefix', 'pattern', 'recursive', 'hash', 'forExclusion', 'excludedPrefixes'];
    }

    /**
     * @internal
     */
    public function __wakeup(): void
    {
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
                $regex = substr_replace($regex, '(/|$)', -2, 1);
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
}
