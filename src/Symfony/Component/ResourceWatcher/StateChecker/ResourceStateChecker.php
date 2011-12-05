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
use Symfony\Component\ResourceWatcher\Event\Event;

/**
 * Abstract resource state checker class.
 *
 * @author Konstantin Kudryashov <ever.zet@gmail.com>
 */
abstract class ResourceStateChecker implements StateCheckerInterface
{
    private $resource;
    private $timestamp;
    private $deleted = false;

    /**
     * Initializes checker.
     *
     * @param   ResourceInterface   $resource
     */
    public function __construct(ResourceInterface $resource)
    {
        $this->resource  = $resource;
        $this->timestamp = $resource->getModificationTime() + 1;
        $this->deleted   = !$resource->exists();
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
     * Check tracked resource for changes.
     *
     * @return  array
     */
    public function getChangeset()
    {
        if ($this->deleted) {
            if (!$this->resource->exists()) {
                return array();
            }

            $this->timestamp = $this->resource->getModificationTime() + 1;
            $this->deleted = false;

            return array(array('event' => Event::CREATED, 'resource' => $this->resource));
        } elseif (!$this->resource->exists()) {
            $this->deleted = true;

            return array(array('event' => Event::DELETED, 'resource' => $this->resource));
        } elseif (!$this->resource->isFresh($this->timestamp)) {
            $this->timestamp = $this->resource->getModificationTime() + 1;

            return array(array('event' => Event::MODIFIED, 'resource' => $this->resource));
        }

        return array();
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
