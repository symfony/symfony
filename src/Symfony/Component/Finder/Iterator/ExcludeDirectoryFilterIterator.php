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

/**
 * ExcludeDirectoryFilterIterator filters out directories.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @extends \FilterIterator<string, \SplFileInfo>
 *
 * @implements \RecursiveIterator<string, \SplFileInfo>
 */
class ExcludeDirectoryFilterIterator extends \FilterIterator implements \RecursiveIterator
{
    private $iterator;
    private $isRecursive;
    private $excludedDirs = [];
    private $excludedPattern;
    private $excludedPatternAbsolute;

    /**
     * @param \Iterator $iterator    The Iterator to filter
     * @param string[]  $directories An array of directories to exclude
     */
    public function __construct(\Iterator $iterator, array $directories)
    {
        $this->iterator = $iterator;
        $this->isRecursive = $iterator instanceof \RecursiveIterator;
        $patterns = [];
        $patternsAbsolute = [];
        foreach ($directories as $directory) {
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
     *
     * @return bool
     */
    #[\ReturnTypeWillChange]
    public function accept()
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

        return true;
    }

    /**
     * @return bool
     */
    #[\ReturnTypeWillChange]
    public function hasChildren()
    {
        return $this->isRecursive && $this->iterator->hasChildren();
    }

    /**
     * @return self
     */
    #[\ReturnTypeWillChange]
    public function getChildren()
    {
        $children = new self($this->iterator->getChildren(), []);
        $children->excludedDirs = $this->excludedDirs;
        $children->excludedPattern = $this->excludedPattern;

        return $children;
    }
}
