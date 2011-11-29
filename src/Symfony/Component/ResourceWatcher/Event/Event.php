<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\ResourceWatcher\Event;

use Symfony\Component\Config\Resource\ResourceInterface;

/**
 * Resource change event.
 *
 * @author Konstantin Kudryashov <ever.zet@gmail.com>
 */
class Event
{
    const CREATED  = 1;
    const MODIFIED = 2;
    const DELETED  = 4;
    const ALL      = 7;

    private $trackingId;
    private $resource;
    private $type;
    private $time;

    /**
     * Initializes resource event.
     *
     * @param   mixed               $trackingId     id of resource inside tracker
     * @param   ResourceInterface   $resource       resource instance
     * @param   integer             $type           event type bit
     */
    public function __construct($trackingId, ResourceInterface $resource, $type)
    {
        $this->trackingId   = $trackingId;
        $this->resource     = $resource;
        $this->type         = $type;
        $this->time         = time();
    }

    /**
     * Returns id of resource inside tracker.
     *
     * @return  integer
     */
    public function getTrackingId()
    {
        return $this->trackingId;
    }

    /**
     * Returns changed resource.
     *
     * @return  ResourceInterface
     */
    public function getResource()
    {
        return $this->resource;
    }

    /**
     * Returns event type.
     *
     * @return  integer
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * Returns time on which event occured.
     *
     * @return  integer
     */
    public function getTime()
    {
        return $this->time;
    }
}
