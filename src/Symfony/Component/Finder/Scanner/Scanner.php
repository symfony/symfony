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

use Symfony\Component\Finder\Exception\AccessDeniedException;
use Symfony\Component\Finder\SplFileInfo;

/**
 * Filesystem scanner.
 *
 * @author Jean-François Simon <contact@jfsimon.fr>
 */
class Scanner implements \IteratorAggregate
{
    private $rootPath;
    private $constraints;
    private $ignoreAccessDenied;
    private $followLinks;
    private $scannedFiles;

    /**
     * Constructor.
     *
     * @param string      $rootPath
     * @param Constraints $constraints
     * @param bool        $ignoreAccessDenied
     * @param bool        $followLinks
     */
    public function __construct($rootPath, Constraints $constraints, $ignoreAccessDenied, $followLinks)
    {
        $this->rootPath = $rootPath;
        $this->constraints = $constraints;
        $this->ignoreAccessDenied = (bool) $ignoreAccessDenied;
        $this->followLinks = (bool) $followLinks;
        $this->scannedFiles = new \ArrayIterator();
    }

    /**
     * Returns an iterator over filtered files.
     *
     * @return \ArrayIterator
     */
    public function getIterator()
    {
        $this->scanDirectory('', 0);

        return $this->scannedFiles;
    }

    /**
     * Scans a directory recursively.
     *
     * @param string $relativePath
     * @param int    $relativeDepth
     * @param bool   $relativePathIncluded
     *
     * @throws AccessDeniedException
     */
    private function scanDirectory($relativePath, $relativeDepth, $relativePathIncluded = false)
    {
        $rootPath = $relativePath ? $this->rootPath.'/'.$relativePath : $this->rootPath;

        if (false === $filenames = @scandir($rootPath)) {
            if ($this->ignoreAccessDenied) {
                return;
            }
            throw new AccessDeniedException(sprintf('Directory "%s" is not readable.', $rootPath));
        }

        $keepFiles = $this->constraints->isMinDepthRespected($relativeDepth);
        $relativeDepth = $relativeDepth + 1;

        foreach ($this->constraints->filterFilenames($filenames) as $filename) {
            $rootPathname = rtrim($rootPath, '/').'/'.$filename;

            if (is_link($rootPathname) && !$this->followLinks) {
                continue;
            }

            $relativePathname = $relativePath ? $relativePath.'/'.$filename : $filename;

            if ($this->constraints->isPathnameExcluded($relativePathname)) {
                continue;
            }

            $pathnameIncluded = $relativePathIncluded || $this->constraints->isPathnameIncluded($relativePathname);

            if (is_dir($rootPathname)) {
                if ($keepFiles && $pathnameIncluded && $this->constraints->isDirectoryKept($relativePathname, $filename)) {
                    $this->scannedFiles[$rootPathname] = new SplFileInfo($rootPathname, $relativePath, $relativePathname);
                }

                if (!$this->constraints->isMaxDepthExceeded($relativeDepth)) {
                    $this->scanDirectory($relativePathname, $relativeDepth, $pathnameIncluded);
                }
            } elseif ($keepFiles && $pathnameIncluded && $this->constraints->isFileKept($relativePathname, $filename)) {
                $this->scannedFiles[$rootPathname] = new SplFileInfo($rootPathname, $relativePath, $relativePathname);
            }
        }
    }
}
