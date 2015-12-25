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
 * This iterator just overrides the rewind method in order to correct a PHP bug,
 * which existed before version 5.5.23/5.6.7.
 *
 * @see https://bugs.php.net/bug.php?id=68557
 *
 * @author Alex Bogomazov
 */
abstract class FilterIterator extends \FilterIterator
{
    /**
     * This is a workaround for the problem with \FilterIterator leaving inner \FilesystemIterator in wrong state after
     * rewind in some cases.
     *
     * @see FilterIterator::rewind()
     */
    public function rewind()
    {
        if (PHP_VERSION_ID < 50523 || PHP_VERSION_ID >= 50600 && PHP_VERSION_ID < 50607) {
            $iterator = $this;
            while ($iterator instanceof \OuterIterator) {
                $innerIterator = $iterator->getInnerIterator();

                if ($innerIterator instanceof RecursiveDirectoryIterator) {
                    if ($innerIterator->isRewindable()) {
                        $innerIterator->next();
                        $innerIterator->rewind();
                    }
                } elseif ($iterator->getInnerIterator() instanceof \FilesystemIterator) {
                    $iterator->getInnerIterator()->next();
                    $iterator->getInnerIterator()->rewind();
                }
                $iterator = $iterator->getInnerIterator();
            }
        }
        parent::rewind();
    }
}
