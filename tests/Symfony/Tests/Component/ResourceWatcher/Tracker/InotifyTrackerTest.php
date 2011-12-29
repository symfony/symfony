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

use Symfony\Component\ResourceWatcher\Tracker\InotifyTracker;

class InotifyTrackerTest extends TrackerTest
{
    public function setUp()
    {
        if (!function_exists('inotify_init')) {
            $this->markTestSkipped('Inotify is required for this test');
        }

        parent::setUp();
    }

    /**
     * @return TrackerInterface
     */
    protected function getTracker()
    {
        return new InotifyTracker();
    }

    protected function getMiminumInterval()
    {
        return 100;
    }
}
