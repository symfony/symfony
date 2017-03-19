<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\EventDispatcher;

/**
 * Keeps track of events whose propagation has been stopped.
 *
 * @author Daniel Santamaría <santaka87@gmail.com>
 */
interface EventPropagationTrackerInterface
{
    /**
     * Checks if the event propagation has been stopped.
     *
     * @param object $event
     *
     * @return bool
     */
    public function isPropagationStopped($event);

    /**
     * Stops the event the propagation.
     *
     * @param object $event
     */
    public function stopPropagation($event);
}
