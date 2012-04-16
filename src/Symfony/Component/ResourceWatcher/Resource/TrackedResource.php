<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\ResourceWatcher\Resource;

use Symfony\Component\Config\Resource\ResourceInterface;
use Symfony\Component\ResourceWatcher\Exception\InvalidArgumentException;

/**
 * Wraps usual resource with tracker information.
 *
 * @author Konstantin Kudryashov <ever.zet@gmail.com>
 */
class TrackedResource
{
    private $trackingId;
    private $resource;

    /**
     * Initializes tracked resource.
     *
     * @param string            $trackingId id of the tracked resource
     * @param ResourceInterface $resource   resource
     */
    public function __construct($trackingId, ResourceInterface $resource)
    {
        if (!$resource->exists()) {
            throw new InvalidArgumentException(sprintf(
                'Unable to track a non-existent resource (%s)', $resource
            ));
        }

        $this->trackingId = $trackingId;
        $this->resource   = $resource;
    }

    /**
     * Returns tracking ID of the resource.
     *
     * @return string
     */
    public function getTrackingId()
    {
        return $this->trackingId;
    }

    /**
     * Returns original resource instance.
     *
     * @return ResourceInterface
     */
    public function getOriginalResource()
    {
        return $this->resource;
    }
}
