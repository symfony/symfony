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

use Symfony\Component\Config\Resource\ResourceInterface;

/**
 * Resources tracker interface.
 *
 * @author Konstantin Kudryashov <ever.zet@gmail.com>
 */
interface TrackerInterface
{
    /**
     * Starts to track provided resource for changes.
     *
     * @param   ResourceInterface   $resource
     */
    function track(ResourceInterface $resource);

    /**
     * Checks whether provided resource is tracked by this tracker.
     *
     * @param   ResourceInterface   $resource
     *
     * @return  Boolean
     */
    function isResourceTracked(ResourceInterface $resource);

    /**
     * Checks tracked resources for change events.
     *
     * @return  array   change events array
     */
    function getEvents();
}
