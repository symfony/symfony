<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\ResourceWatcher;

use Symfony\Component\Config\Resource\ResourceInterface;
use Symfony\Component\Config\Resource\FileResource;
use Symfony\Component\Config\Resource\DirectoryResource;
use Symfony\Component\ResourceWatcher\Event\Event;
use Symfony\Component\ResourceWatcher\Event\EventListener;
use Symfony\Component\ResourceWatcher\Event\EventListenerInterface;
use Symfony\Component\ResourceWatcher\Tracker\TrackerInterface;
use Symfony\Component\ResourceWatcher\Tracker\InotifyTracker;
use Symfony\Component\ResourceWatcher\Tracker\RecursiveIteratorTracker;

/**
 * Resources changes watcher.
 *
 * @author Konstantin Kudryashov <ever.zet@gmail.com>
 */
class ResourceWatcher
{
    private $tracker;
    private $watching  = true;
    private $listeners = array();

    /**
     * Initializes path watcher.
     *
     * @param   TrackerInterface  $tracker
     */
    public function __construct(TrackerInterface $tracker = null)
    {
        if (null !== $tracker) {
            $this->tracker = $tracker;
        } elseif (function_exists('inotify_init')) {
            $this->tracker = new InotifyTracker();
        } else {
            $this->tracker = new RecursiveIteratorTracker();
        }
    }

    /**
     * Returns current tracker instance.
     *
     * @return  TrackerInterface
     */
    public function getTracker()
    {
        return $this->tracker;
    }

    /**
     * Track resource with watcher.
     *
     * @param   ResourceInterface|string    $resource   resource to track
     * @param   callable                    $callback   event callback
     * @param   integer                     $eventsMask event types bitmask
     */
    public function track($resource, $callback, $eventsMask = Event::ALL)
    {
        if (!$resource instanceof ResourceInterface) {
            if (is_file($resource)) {
                $resource = new FileResource($resource);
            } elseif (is_dir($resource)) {
                $resource = new DirectoryResource($resource);
            } else {
                throw new \InvalidArgumentException(sprintf(
                    'First argument to track() should be either file or directory resource, but got "%s"', $resource
                ));
            }
        }

        if (!is_callable($callback)) {
            throw new \InvalidArgumentException('Second argument to track() should be callable.');
        }

        $this->addListener(new EventListener($resource, $callback, $eventsMask));
    }

    /**
     * Adds resource event listener to watcher.
     *
     * @param   EventListenerInterface  $listener   resource event listener
     */
    public function addListener(EventListenerInterface $listener)
    {
        if (!$this->getTracker()->isResourceTracked($listener->getResource())) {
            $this->getTracker()->track($listener->getResource());
        }

        $trackingId = $listener->getResource()->getId();

        $this->listeners[$trackingId][] = $listener;
    }

    /**
     * Returns true if watcher is currently watching on tracked resources (started).
     *
     * @return  Boolean
     */
    public function isWatching()
    {
        return $this->watching;
    }

    /**
     * Starts wathing on tracked resources.
     *
     * @param   integer $checkInterval  check interval in microseconds
     * @param   integer $timeLimit      maximum watching time limit in microseconds
     */
    public function start($checkInterval = 1000000, $timeLimit = null)
    {
        $totalTime = 0;
        $this->watching = true;

        while ($this->watching) {
            usleep($checkInterval);
            $totalTime += $checkInterval;

            if (null !== $timeLimit && $totalTime > $timeLimit) {
                break;
            }

            if (count($events = $this->getTracker()->getEvents())) {
                $this->notifyListeners($events);
            }
        }
    }

    /**
     * Stop watching on tracked resources.
     */
    public function stop()
    {
        $this->watching = false;
    }

    /**
     * Notifies all registered resource event listeners about their events.
     *
     * @param   array   $events     array of resource events
     */
    private function notifyListeners(array $events)
    {
        foreach ($events as $event) {
            $trackingId = $event->getTrackingId();

            if (isset($this->listeners[$trackingId])) {
                foreach ($this->listeners[$trackingId] as $listener) {
                    if ($listener->supports($event)) {
                        call_user_func($listener->getCallback(), $event);
                    }
                }
            }
        }
    }
}
