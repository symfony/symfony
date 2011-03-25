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

/**
 * Resource change listener interface.
 *
 * @author Konstantin Kudryashov <ever.zet@gmail.com>
 */
interface EventListenerInterface
{
    /**
     * Returns listening resource.
     *
     * @return  ResourceInterface
     */
    function getResource();

    /**
     * Returns callback.
     *
     * @return  callable
     */
    function getCallback();

    /**
     * Checks whether listener supports provided resource event.
     *
     * @param   Event   $event
     */
    function supports(Event $event);
}
