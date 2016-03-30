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
 * The EventInterface is the base interface for classes containing event data.
 *
 * You can call the method stopPropagation() to abort the execution of
 * further listeners in your event listener.
 *
 * @author Tomasz Świacko-Świackiewicz <tomasz.swiacko.swiackiewicz@gmail.com>
 */
interface EventInterface
{
    /**
     * Returns whether further event listeners should be triggered.
     *
     * @see EventInterface::stopPropagation()
     *
     * @return bool Whether propagation was already stopped for this event.
     */
    public function isPropagationStopped();

    /**
     * Stops the propagation of the event to further event listeners.
     *
     * If multiple event listeners are connected to the same event, no
     * further event listener will be triggered once any trigger calls
     * stopPropagation().
     */
    public function stopPropagation();
}
