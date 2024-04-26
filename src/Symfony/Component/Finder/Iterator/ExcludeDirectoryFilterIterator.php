<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Finder\Iterator;

use Symfony\Component\Finder\SplFileInfo;

/**
 * ExcludeDirectoryFilterIterator filters out directories.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @extends \FilterIterator<string, SplFileInfo>
 *
 * @implements \RecursiveIterator<string, SplFileInfo>
 */
class ExcludeDirectoryFilterIterator extends \FilterIterator implements \RecursiveIterator
{
    /** @var \Iterator<string, SplFileInfo> */
    private \Iterator $iterator;
    private bool $isRecursive;
    /** @var array<string, true> */
    private array $excludedDirs = [];
    private ?string $excludedPattern = null;
    private ?string $excludedPatternAbsolute = null;
    /** @var list<callable(SplFileInfo):bool> */
    private array $pruneFilters = [];

    /**
     * @param \Iterator<string, SplFileInfo>          $iterator    The Iterator to filter
     * @param list<string|callable(SplFileInfo):bool> $directories An array of directories to exclude
     */
    public function __construct(\Iterator $iterator, array $directories)
    {
        $this->iterator = $iterator;
        $this->isRecursive = $iterator instanceof \RecursiveIterator;
        $patterns = [];
        $patternsAbsolute = [];
        foreach ($directories as $directory) {
            if (!\is_string($directory)) {
                if (!\is_callable($directory)) {
                    throw new \InvalidArgumentException('Invalid PHP callback.');
                }

                $this->pruneFilters[] = $directory;

                continue;
            }

            $directory = rtrim($directory, '/');
            $slashPos = strpos($directory, '/');
            if (false !== $slashPos && \strlen($directory) - 1 !== $slashPos) {
                if (0 === $slashPos) {
                    $directory = substr($directory, 1);
                }
                $patternsAbsolute[] = preg_quote($directory, '#');
            } elseif (!$this->isRecursive || str_contains($directory, '/')) {
                $patterns[] = preg_quote($directory, '#');
            } else {
                $this->excludedDirs[$directory] = true;
            }
        }
        if ($patterns) {
            $this->excludedPattern = '#(?:^|/)(?:'.implode('|', $patterns).')(?:/|$)#';
        }

        if ($patternsAbsolute) {
            $this->excludedPatternAbsolute = '#^('.implode('|', $patternsAbsolute).')$#';
        }

        parent::__construct($iterator);
    }

    /**
     * Filters the iterator values.
     */
    public function accept(): bool
    {
        if ($this->isRecursive && isset($this->excludedDirs[$this->getFilename()]) && $this->isDir()) {
            return false;
        }

        if ($this->excludedPattern || $this->excludedPatternAbsolute) {
            $path = $this->isDir() ? $this->current()->getRelativePathname() : $this->current()->getRelativePath();
            $path = str_replace('\\', '/', $path);
        }
        if ($this->excludedPattern && preg_match($this->excludedPattern, $path)) {
            return false;
        }
        if ($this->excludedPatternAbsolute && preg_match($this->excludedPatternAbsolute, $path)) {
            return false;
        }

        if ($this->pruneFilters && $this->hasChildren()) {
            foreach ($this->pruneFilters as $pruneFilter) {
                if (!$pruneFilter($this->current())) {
                    return false;
                }
            }
        }

        return true;
    }

    public function hasChildren(): bool
    {
        return $this->isRecursive && $this->iterator->hasChildren();
    }

    public function getChildren(): self
    {
        $children = new self($this->iterator->getChildren(), []);
        $children->excludedDirs = $this->excludedDirs;
        $children->excludedPattern = $this->excludedPattern;

        return $children;
    }
}
