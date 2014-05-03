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
 * Algorithm for comparing files against their changed times.
 *
 * @author Hugo Hamon <hugo.hamon@sensiolabs.com>
 */
class ChangedTimeComparator
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
        return $a->getCTime() - $b->getCTime();
    }
}
