<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\ResourceWatcher\StateChecker;

use Symfony\Component\Config\Resource\ResourceInterface;
use Symfony\Component\ResourceWatcher\Event\FilesystemEvent;

/**
 * Abstract resource state checker class.
 *
 * @author Konstantin Kudryashov <ever.zet@gmail.com>
 */
abstract class ResourceStateChecker implements StateCheckerInterface
{
    private $resource;
    private $timestamp;
    private $eventsMask;
    private $deleted = false;

    /**
     * Initializes checker.
     *
     * @param   ResourceInterface $resource   resource
     * @param   integer           $eventsMask event types bitmask
     */
    public function __construct(ResourceInterface $resource, $eventsMask = FilesystemEvent::IN_ALL)
    {
        $this->resource   = $resource;
        $this->timestamp  = $resource->getModificationTime() + 1;
        $this->eventsMask = $eventsMask;
        $this->deleted    = !$resource->exists();
    }

    /**
     * Returns tracked resource.
     *
     * @return  ResourceInterface
     */
    public function getResource()
    {
        return $this->resource;
    }

    /**
     * Returns events mask for checker.
     *
     * @return  integer
     */
    public function getEventsMask()
    {
        return $this->eventsMask;
    }

    /**
     * Check tracked resource for changes.
     *
     * @return  array
     */
    public function getChangeset()
    {
        $changeset = array();

        if ($this->deleted) {
            if ($this->resource->exists()) {
                $this->timestamp = $this->resource->getModificationTime() + 1;
                $this->deleted   = false;

                if ($this->supportsEvent($event = FilesystemEvent::IN_CREATE)) {
                    $changeset[] = array(
                        'event'    => $event,
                        'resource' => $this->resource
                    );
                }
            }
        } elseif (!$this->resource->exists()) {
            $this->deleted = true;

            if ($this->supportsEvent($event = FilesystemEvent::IN_DELETE)) {
                $changeset[] = array(
                    'event'    => $event,
                    'resource' => $this->resource
                );
            }
        } elseif (!$this->resource->isFresh($this->timestamp)) {
            $this->timestamp = $this->resource->getModificationTime() + 1;

            if ($this->supportsEvent($event = FilesystemEvent::IN_MODIFY)) {
                $changeset[] = array(
                    'event'    => $event,
                    'resource' => $this->resource
                );
            }
        }

        return $changeset;
    }

    /**
     * Checks whether checker supports provided resource event.
     *
     * @param   integer   $event
     */
    protected function supportsEvent($event)
    {
        return 0 !== ($this->eventsMask & $event);
    }

    /**
     * Checks whether resource have been previously deleted.
     *
     * @return  Boolean
     */
    protected function isDeleted()
    {
        return $this->deleted;
    }
}
