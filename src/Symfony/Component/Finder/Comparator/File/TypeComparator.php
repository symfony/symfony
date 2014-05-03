<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Finder\Comparator\File;

/**
 * Algorithm for comparing files against their file types.
 *
 * @author Hugo Hamon <hugo.hamon@sensiolabs.com>
 */
class TypeComparator
{
    /**
     * Compares two \SplFileInfo instances when this object
     * is used a valid callable function by PHP arrays sorting
     * functions.
     *
     * @param \SplFileInfo $a
     * @param \SplFileInfo $b
     * @return int
     */
    public function __invoke(\SplFileInfo $a, \SplFileInfo $b)
    {
        if ($a->isDir() && $b->isFile()) {
            return -1;
        }

        if ($a->isFile() && $b->isDir()) {
            return 1;
        }

        return strcmp($a->getRealpath(), $b->getRealpath());
    }
}
