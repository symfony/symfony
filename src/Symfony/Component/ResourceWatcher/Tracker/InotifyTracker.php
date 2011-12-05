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
    private $trackedResources = array();
    private $resourcePaths    = array();

    /**
     * Initializes tracker.
     */
    public function __construct()
    {
        if (!function_exists('inotify_init')) {
            throw new \RuntimeException('You must install inotify to be able to use this tracker.');
        }

        $this->stream = inotify_init();
        stream_set_blocking($this->stream, 0);
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
        $this->watchResource($resource, $resource, realpath($resource->getResource()));
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
        return null !== $this->getTrackingId($resource);
    }

    /**
     * Checks tracked resources for change events.
     *
     * @return  array   change events array
     */
    public function getEvents()
    {
        $events = array();
        if ($iEvents = inotify_read($this->stream)) {
            foreach ($iEvents as $iEvent) {
                $trackingId   = $iEvent['wd'];
                $resourcePath = $this->resourcePaths[$trackingId].DIRECTORY_SEPARATOR.$iEvent['name'];
                $tracked      = $this->trackedResources[$trackingId];

                if ('' == $iEvent['name']) {
                    continue;
                } elseif (is_dir($resourcePath)) {
                    $resource = new DirectoryResource($resourcePath);
                } elseif (is_file($resourcePath)) {
                    $resource = new FileResource($resourcePath);
                } else {
                    continue;
                }

                if ($resource instanceof FileResource) {
                    $file = new \SplFileInfo($resourcePath);
                    if ($tracked instanceof DirectoryResource && !$tracked->hasFile($file)) {
                        continue;
                    }
                }

                if (IN_CREATE === ($iEvent['mask'] & IN_CREATE)) {
                    if ($resource instanceof DirectoryResource) {
                        $this->watchResource($resource, $tracked, $resource->getResource());
                    }
                    $event = Event::CREATED;
                } elseif (IN_MODIFY === ($iEvent['mask'] & IN_MODIFY)) {
                    $event = Event::MODIFIED;
                } elseif (IN_DELETE === ($iEvent['mask'] & IN_DELETE)) {
                    $this->unwatchResource($resource);
                    $event = Event::DELETED;
                }

                $events[] = new Event($tracked->getId(), $resource, $event);
            }
        }

        return $events;
    }

    private function watchResource(ResourceInterface $resource, ResourceInterface $parent, $path)
    {
        $trackingId = inotify_add_watch(
            $this->stream, $resource->getResource(), IN_CREATE | IN_MODIFY | IN_DELETE
        );

        $this->trackedResources[$trackingId] = $parent;

        if (!is_dir($path)) {
            $path = dirname($path);
        }
        $this->resourcePaths[$trackingId] = rtrim($path, DIRECTORY_SEPARATOR);

        if ($resource instanceof DirectoryResource) {
            foreach ($resource->getFilteredResources() as $child) {
                if ($child instanceof DirectoryResource) {
                    $this->watchResource($child, $parent, realpath($child->getResource()));
                }
            }
        }
    }

    private function unwatchResource(ResourceInterface $resource)
    {
        if ($id = $this->getTrackingId($resource)) {
            inotify_rm_watch($this->stream, $id);
            unset($this->resourcePaths[$id]);
            unset($this->trackedResources[$id]);
        }
    }

    private function getTrackingId(ResourceInterface $resource)
    {
        foreach ($this->trackedResources as $trackingId => $trackedResource) {
            if ($trackedResource->getId() === $resource->getId()) {
                return $trackingId;
            }
        }
    }
}
