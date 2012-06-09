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
use Symfony\Component\ResourceWatcher\Resource\TrackedResource;
use Symfony\Component\ResourceWatcher\Event\FilesystemEvent;
use Symfony\Component\ResourceWatcher\StateChecker\Inotify\TopDirectoryStateChecker;
use Symfony\Component\ResourceWatcher\StateChecker\Inotify\FileStateChecker;
use Symfony\Component\ResourceWatcher\Exception\RuntimeException;
use Symfony\Component\ResourceWatcher\StateChecker\Inotify\CheckerBag;

/**
 * Inotify tracker. To use this tracker you must install inotify extension.
 *
 * @link http://pecl.php.net/package/inotify Inotify PECL extension
 * @author Yaroslav Kiliba <om.dattaya@gmail.com>
 */
class InotifyTracker implements TrackerInterface
{
    /**
     * @var array
     */
    protected $checkers = array();

    /**
     * @var CheckerBag
     */
    protected $bag;

    /**
     * @var resource Inotify resource.
     */
    private $inotify;

    /**
     * Initializes tracker. Creates inotify resource used to track file and directory changes.
     *
     * @throws RuntimeException If inotify extension unavailable
     */
    public function __construct()
    {
        if (!function_exists('inotify_init')) {
            throw new RuntimeException('You must install inotify to be able to use this tracker.');
        }

        $this->inotify = inotify_init();
        stream_set_blocking($this->inotify, 0);

        $this->bag = new CheckerBag($this->inotify);
    }

    /**
     * {@inheritdoc}
     */
    public function track(TrackedResource $resource, $eventsMask = FilesystemEvent::IN_ALL)
    {
        $trackingId = $resource->getTrackingId();
        $checker    = $resource->getOriginalResource() instanceof DirectoryResource
            ? new TopDirectoryStateChecker($this->bag, $resource->getOriginalResource(), $eventsMask)
            : new FileStateChecker($this->bag, $resource->getOriginalResource(), $eventsMask);

        $this->checkers[$trackingId] = array(
            'tracked' => $resource,
            'checker' => $checker
        );
    }

    /**
     * {@inheritdoc}
     *
     * @throws RuntimeException If event queue overflowed
     */
    public function getEvents()
    {
        $inotifyEvents = $this->readEvents();

        $inotifyEvents = is_array($inotifyEvents) ? $inotifyEvents : array();

        $last = end($inotifyEvents);
        if (IN_Q_OVERFLOW === $last['mask']) {
            throw new RuntimeException('Event queue overflowed. Either read events more frequently or increase the limit for queues. The limit can be changed in /proc/sys/fs/inotify/max_queued_events');
        }

        foreach ($inotifyEvents as $event) {
            foreach ($this->bag->get($event['wd']) as $watched) {
                $watched->setEvent($event['mask'], $event['name']);
            }
        }

        $events = array();

        foreach ($this->checkers as $meta) {
            $tracked = $meta['tracked'];
            $watched = $meta['checker'];
            foreach ($watched->getChangeset() as $change) {
                $events[] = new FilesystemEvent($tracked, $change['resource'], $change['event']);
            }
        }

        return $events;
    }

    /**
     * Closes the inotify resource.
     */
    public function __destruct()
    {
        fclose($this->inotify);
    }

    /**
     * Returns all events happened since last event readout
     *
     * @return array
     */
    protected function readEvents()
    {
        return inotify_read($this->inotify);
    }
}
