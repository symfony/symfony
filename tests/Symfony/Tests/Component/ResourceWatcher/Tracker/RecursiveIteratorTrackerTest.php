<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Tests\Component\ResourceWatcher\Tracker;

use Symfony\Component\ResourceWatcher\Tracker\RecursiveIteratorTracker;

class RecursiveIteratorTrackerTest extends TrackerTest
{
    /**
     * @return TrackerInterface
     */
    protected function getTracker()
    {
        return new RecursiveIteratorTracker();
    }

    protected function getMiminumInterval()
    {
        return 2000000;
    }
}
