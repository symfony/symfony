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
    private ?string $excludedRootPattern = null;
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
        $rootPatterns = [];
        foreach ($directories as $directory) {
            if (!\is_string($directory)) {
                if (!\is_callable($directory)) {
                    throw new \InvalidArgumentException('Invalid PHP callback.');
                }

                $this->pruneFilters[] = $directory;

                continue;
            }

            $directory = rtrim($directory, '/');
            if (str_starts_with($directory, '/')) {
                $rootPatterns[] = preg_quote(substr($directory, 1), '#');
            } elseif (!$this->isRecursive || str_contains($directory, '/')) {
                $patterns[] = preg_quote($directory, '#');
            } else {
                $this->excludedDirs[$directory] = true;
            }
        }
        if ($patterns) {
            $this->excludedPattern = '#(?:^|/)(?:'.implode('|', $patterns).')(?:/|$)#';
        }
        if ($rootPatterns) {
            $this->excludedRootPattern = '#^(?:'.implode('|', $rootPatterns).')(?:/|$)#';
        }

        parent::__construct($iterator);
    }

    /**
     * Filters the iterator values.
     */
    public function accept(): bool
    {
        if ($this->isRecursive && isset($this->excludedDirs[$this->current()->getFilename()]) && $this->current()->isDir()) {
            return false;
        }

        if ($this->excludedPattern || $this->excludedRootPattern) {
            $path = $this->current()->isDir() ? $this->current()->getRelativePathname() : $this->current()->getRelativePath();
            $path = str_replace('\\', '/', $path);

            if ($this->excludedPattern && preg_match($this->excludedPattern, $path)) {
                return false;
            }

            if ($this->excludedRootPattern && preg_match($this->excludedRootPattern, $path)) {
                return false;
            }
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
        $children->excludedRootPattern = $this->excludedRootPattern;
        $children->pruneFilters = $this->pruneFilters;

        return $children;
    }
}
