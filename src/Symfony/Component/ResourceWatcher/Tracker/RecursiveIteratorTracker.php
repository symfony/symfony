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
use Symfony\Component\ResourceWatcher\StateChecker\StateCheckerInterface;
use Symfony\Component\ResourceWatcher\StateChecker\RecursiveIteratorStateChecker;

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
     * @param   ResourceInterface   $resource
     */
    public function track(ResourceInterface $resource)
    {
        $this->addStateChecker(new RecursiveIteratorStateChecker($resource));
    }

    /**
     * Adds state checker to tracker.
     *
     * @param   StateCheckerInterface   $checker
     */
    public function addStateChecker(StateCheckerInterface $checker)
    {
        $this->checkers[$this->getResourceTrackingId($checker->getResource())] = $checker;
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
        return isset($this->checkers[$this->getResourceTrackingId($resource)]);
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
        return md5((string) $resource);
    }

    /**
     * Checks tracked resources for changes.
     *
     * @return  array   change events array
     */
    public function checkChanges()
    {
        $events = array();
        foreach ($this->checkers as $trackingId => $checker) {
            $changeset = $checker->checkChanges();

            foreach ($changeset as $type => $resources) {
                switch ($type) {
                    case 'created':
                        $eventType = Event::CREATED;
                        break;
                    case 'modified':
                        $eventType = Event::MODIFIED;
                        break;
                    case 'deleted':
                        $eventType = Event::DELETED;
                        break;
                }

                foreach ($resources as $resource) {
                    $events[] = new Event($trackingId, $resource, $eventType);
                }
            }
        }

        return $events;
    }
}
