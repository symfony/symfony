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

use Symfony\Component\ResourceWatcher\Event\Event;
use Symfony\Component\Config\Resource\ResourceInterface;
use Symfony\Component\Config\Resource\DirectoryResource;
use Symfony\Component\Config\Resource\FileResource;

/**
 * Inotify events resources tracker.
 *
 * @author Konstantin Kudryashov <ever.zet@gmail.com>
 */
class InotifyTracker implements TrackerInterface
{
    private $stream;
    private $trackingIds = array();
    private $tracks = array();

    /**
     * Initializes tracker.
     */
    public function __construct()
    {
        $this->stream = inotify_init();
    }

    /**
     * Destructs tracker.
     */
    public function __destruct()
    {
        fclose($this->stream);
    }

    /**
     * Starts to track provided resource for changes.
     *
     * @param   ResourceInterface   $resource
     */
    public function track(ResourceInterface $resource)
    {
        $trackingId = inotify_add_watch(
            $this->stream, $resource->getResource(), IN_CREATE | IN_MODIFY | IN_DELETE
        );
        $this->trackingIds[$resource->getResource()] = $trackingId;

        $this->tracks[$trackingId] = $resource;
    }

    /**
     * Checks whether provided resource is tracked by this tracker.
     *
     * @param   ResourceInterface   $resource
     *
     * @return  Boolean
     */
    public function isResourceTracked(ResourceInterface $resource)
    {
        return null !== $this->getResourceTrackingId($resource);
    }

    /**
     * Returns resource tracking ID.
     *
     * @param   ResourceInterface   $resource
     *
     * @return  mixed
     */
    public function getResourceTrackingId(ResourceInterface $resource)
    {
        return isset($this->trackingIds[$resource->getResource()])
             ? $this->trackingIds[$resource->getResource()]
             : null;
    }

    /**
     * Checks tracked resources for changes.
     *
     * @return  array   change events array
     */
    public function checkChanges()
    {
        $events = array();

        if ($iEvents = inotify_read($this->stream)) {
            foreach ($iEvents as $iEvent) {
                $trackingId = $iEvent['wd'];

                if (isset($this->tracks[$trackingId])) {
                    $resource = $this->tracks[$trackingId];
                } else {
                    if (is_dir($iEvent['name'])) {
                        $resource = new DirectoryResource($iEvent['name']);
                    } else {
                        $resource = new FileResource($iEvent['name']);
                    }
                }

                if ($iEvent['mask'] & IN_CREATE) {
                    $event = Event::CREATED;
                } elseif ($iEvent['mask'] & IN_MODIFY) {
                    $event = Event::MODIFIED;
                } elseif ($iEvent['mask'] & IN_DELETE) {
                    $event = Event::DELETED;
                }

                $events[] = new Event($trackingId, $resource, $event);
            }
        }

        return $events;
    }
}
