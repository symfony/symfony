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
use Symfony\Component\Config\Resource\FileResource;
use Symfony\Component\ResourceWatcher\Resource\TrackedResource;
use Symfony\Component\ResourceWatcher\Event\FilesystemEvent;
use Symfony\Component\ResourceWatcher\Exception\RuntimeException;
use Symfony\Component\ResourceWatcher\Exception\InvalidArgumentException;

/**
 * Inotify events resources tracker.
 *
 * @author Konstantin Kudryashov <ever.zet@gmail.com>
 */
class InotifyTracker implements TrackerInterface
{
    private $inotify;
    private $trackedResources = array();

    /**
     * Initializes tracker.
     */
    public function __construct()
    {
        if (!function_exists('inotify_init')) {
            throw new RuntimeException('You must install inotify to be able to use this tracker.');
        }

        $this->inotify = inotify_init();
        stream_set_blocking($this->inotify, 0);
    }

    /**
     * Destructs tracker.
     */
    public function __destruct()
    {
        fclose($this->inotify);
    }

    /**
     * Starts to track provided resource for changes.
     *
     * @param   TrackedResource   $resource
     * @param   integer           $eventsMask event types bitmask
     */
    public function track(TrackedResource $resource, $eventsMask = FilesystemEvent::IN_ALL)
    {
        $originalResource = $resource->getOriginalResource();

        $mask = 0;
        if (0 !== ($eventsMask & FilesystemEvent::IN_CREATE)) {
            $mask |= IN_CREATE;
        }
        if (0 !== ($eventsMask & FilesystemEvent::IN_MODIFY)) {
            $mask |= IN_MODIFY;
        }
        if (0 !== ($eventsMask & FilesystemEvent::IN_DELETE)) {
            $mask |= IN_DELETE;
        }

        $id = inotify_add_watch($this->inotify, $originalResource->getResource(), $mask);
        $this->trackedResources[$id] = $resource;
    }

    /**
     * Checks tracked resources for change events.
     *
     * @return  array   change events array
     */
    public function getEvents()
    {
        $events = array();

        if (0 === inotify_queue_len($this->inotify)) {
            return $events;
        }

        foreach (inotify_read($this->inotify) as $iEvent) {
            $id      = $iEvent['wd'];
            $tracked = $this->trackedResources[$id];

            if ('' === $iEvent['name']) {
                continue;
            }

            $resource = $iEvent['name'];
        }

        return $events;
    }
}
