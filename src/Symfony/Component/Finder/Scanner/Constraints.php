<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Finder\Scanner;

/**
 * Scanner's files filtering constraints.
 *
 * @author Jean-François Simon <contact@jfsimon.fr>
 */
class Constraints
{
    const TYPE_ALL = 0;
    const TYPE_FILES = 1;
    const TYPE_DIRECTORIES = 2;

    private $excludedNames;
    private $minDepthRespected;
    private $maxDepthExceeded;
    private $pathnameExcluded;
    private $pathnameIncluded;
    private $keep;
    private $keepDirectories;
    private $keepFiles;

    /**
     * Constructor.
     *
     * @param int   $type
     * @param int   $minDepth
     * @param int   $maxDepth
     * @param array $excludedNames
     * @param array $pathnameConstraints
     * @param array $filenameConstraints
     */
    public function __construct($type = self::TYPE_ALL, $minDepth = 0, $maxDepth = PHP_INT_MAX, array $excludedNames = array(), array $pathnameConstraints = array(), array $filenameConstraints = array())
    {
        $this->excludedNames = $excludedNames;

        if (0 !== $minDepth) {
            $this->minDepthRespected = function ($depth) use ($minDepth) { return $depth >= $minDepth; };
        }

        if (PHP_INT_MAX !== $maxDepth) {
            $this->maxDepthExceeded = function ($depth) use ($maxDepth) { return $depth > $maxDepth; };
        }

        if (isset($pathnameConstraints['excluded_patterns'])) {
            $this->pathnameExcluded = self::buildPathnameTest($pathnameConstraints['excluded_patterns']);
        }

        if (isset($pathnameConstraints['included_patterns'])) {
            $this->pathnameIncluded = self::buildPathnameTest($pathnameConstraints['included_patterns']);
        }

        if (!empty($filenameConstraints) || isset($pathnameConstraints['included_ending_patterns']) || isset($pathnameConstraints['excluded_ending_patterns'])) {
            $this->keep = self::buildKeptTest($filenameConstraints, $pathnameConstraints);
        }

        $this->keepDirectories = self::TYPE_FILES !== $type;
        $this->keepFiles = self::TYPE_DIRECTORIES !== $type;
    }

    /**
     * Filters file names.
     *
     * @param array $filenames
     *
     * @return array
     */
    public function filterFilenames(array $filenames)
    {
        return array_diff($filenames, $this->excludedNames);
    }

    /**
     * Tests if min depth constraint is respected.
     *
     * @param int $depth
     *
     * @return bool
     */
    public function isMinDepthRespected($depth)
    {
        if (!$this->minDepthRespected instanceof \Closure) {
            return true;
        }

        return call_user_func($this->minDepthRespected, $depth);
    }

    /**
     * Tests if max depth constraint is exceeded.
     *
     * @param int $depth
     *
     * @return bool
     */
    public function isMaxDepthExceeded($depth)
    {
        if (!$this->maxDepthExceeded instanceof \Closure) {
            return false;
        }

        return call_user_func($this->maxDepthExceeded, $depth);
    }

    /**
     * Tests if pathname is excluded.
     *
     * @param string $pathname
     *
     * @return bool
     */
    public function isPathnameExcluded($pathname)
    {
        if (!$this->pathnameExcluded instanceof \Closure) {
            return false;
        }

        return call_user_func($this->pathnameExcluded, $pathname);
    }

    /**
     * Tests if pathname is included.
     *
     * @param string $pathname
     *
     * @return bool
     */
    public function isPathnameIncluded($pathname)
    {
        if (!$this->pathnameIncluded instanceof \Closure) {
            return true;
        }

        return call_user_func($this->pathnameIncluded, $pathname);
    }

    /**
     * Tests if directory must be kept.
     *
     * @param string $pathname
     * @param string $filename
     *
     * @return bool
     */
    public function isDirectoryKept($pathname, $filename)
    {
        if (!$this->keepDirectories) {
            return false;
        }

        if (!$this->keep instanceof \Closure) {
            return true;
        }

        return call_user_func($this->keep, $pathname, $filename);
    }

    /**
     * Tests if file must be kept.
     *
     * @param string $pathname
     * @param string $filename
     *
     * @return bool
     */
    public function isFileKept($pathname, $filename)
    {
        if (!$this->keepFiles) {
            return false;
        }

        if (!$this->keep instanceof \Closure) {
            return true;
        }

        return call_user_func($this->keep, $pathname, $filename);
    }

    /**
     * Builds a pathname test closure.
     *
     * @param string[] $patterns
     *
     * @return \Closure
     */
    private static function buildPathnameTest(array $patterns)
    {
        return function ($value) use ($patterns) {
            foreach ($patterns as $pattern) {
                if (preg_match($pattern, $value)) {
                    return true;
                }
            }

            return false;
        };
    }

    /**
     * Builds a keep file test closure.
     *
     * @param array $filenameConstraints
     * @param array $pathnameConstraints
     *
     * @return \Closure
     */
    private static function buildKeptTest(array $filenameConstraints, array $pathnameConstraints)
    {
        $excludedTests = array();
        $includedTests = array();

        if (isset($filenameConstraints['excluded_filenames'])) {
            $filenames = $filenameConstraints['excluded_filenames'];
            $excludedTests[] = function ($pathname, $filename) use ($filenames) {
                return in_array($filename, $filenames);
            };
        }

        if (isset($filenameConstraints['excluded_patterns'])) {
            $patterns = $filenameConstraints['excluded_patterns'];
            $excludedTests[] = function ($pathname, $filename) use ($patterns) {
                foreach ($patterns as $pattern) {
                    if (preg_match($pattern, $filename)) {
                        return true;
                    }
                }

                return false;
            };
        }

        if (isset($pathnameConstraints['excluded_ending_patterns'])) {
            $patterns = $pathnameConstraints['excluded_ending_patterns'];
            $excludedTests[] = function ($pathname, $filename) use ($patterns) {
                foreach ($patterns as $pattern) {
                    if (preg_match($pattern, $pathname)) {
                        return true;
                    }
                }

                return false;
            };
        }

        if (isset($filenameConstraints['included_filenames'])) {
            $filenames = $filenameConstraints['included_filenames'];
            $includedTests[] = function ($pathname, $filename) use ($filenames) {
                return in_array($filename, $filenames);
            };
        }

        if (isset($filenameConstraints['included_patterns'])) {
            $patterns = $filenameConstraints['included_patterns'];
            $includedTests[] = function ($pathname, $filename) use ($patterns) {
                foreach ($patterns as $pattern) {
                    if (preg_match($pattern, $filename)) {
                        return true;
                    }
                }

                return false;
            };
        }

        if (isset($pathnameConstraints['included_ending_patterns'])) {
            $patterns = $pathnameConstraints['included_ending_patterns'];
            $includedTests[] = function ($pathname, $filename) use ($patterns) {
                foreach ($patterns as $pattern) {
                    if (preg_match($pattern, $pathname)) {
                        return true;
                    }
                }

                return false;
            };
        }

        return function ($pathname, $filename) use ($excludedTests, $includedTests) {
            foreach ($excludedTests as $excludeTest) {
                if ($excludeTest($pathname, $filename)) {
                    return false;
                }
            }

            foreach ($includedTests as $includedTest) {
                if ($includedTest($pathname, $filename)) {
                    return true;
                }
            }

            return empty($includedTests);
        };
    }
}
