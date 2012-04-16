<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\ResourceWatcher\Tracker;

use Symfony\Component\Config\Resource\DirectoryResource;
use Symfony\Component\ResourceWatcher\Event\FilesystemEvent;
use Symfony\Component\ResourceWatcher\Resource\TrackedResource;
use Symfony\Component\ResourceWatcher\StateChecker\DirectoryStateChecker;
use Symfony\Component\ResourceWatcher\StateChecker\FileStateChecker;

/**
 * Recursive iterator resources tracker.
 *
 * @author Konstantin Kudryashov <ever.zet@gmail.com>
 */
class RecursiveIteratorTracker implements TrackerInterface
{
    private $checkers = array();

    /**
     * Starts to track provided resource for changes.
     *
     * @param   TrackedResource   $resource
     * @param   integer           $eventsMask event types bitmask
     */
    public function track(TrackedResource $resource, $eventsMask = FilesystemEvent::IN_ALL)
    {
        $trackingId = $resource->getTrackingId();
        $checker    = $resource->getOriginalResource() instanceof DirectoryResource
            ? new DirectoryStateChecker($resource->getOriginalResource(), $eventsMask)
            : new FileStateChecker($resource->getOriginalResource(), $eventsMask);

        $this->checkers[$trackingId] = array(
            'tracked' => $resource,
            'checker' => $checker
        );
    }

    /**
     * Checks tracked resources for change events.
     *
     * @return  array   change events array
     */
    public function getEvents()
    {
        $events = array();
        foreach ($this->checkers as $trackingId => $meta) {
            $tracked = $meta['tracked'];
            $checker = $meta['checker'];

            foreach ($checker->getChangeset() as $change) {
                $events[] = new FilesystemEvent($tracked, $change['resource'], $change['event']);
            }
        }

        return $events;
    }
}
