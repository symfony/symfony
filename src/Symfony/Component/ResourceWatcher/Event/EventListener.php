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
 * Resource change listener.
 *
 * @author Konstantin Kudryashov <ever.zet@gmail.com>
 */
class EventListener implements EventListenerInterface
{
    private $resource;
    private $callback;
    private $eventsMask;

    /**
     * Initializes listener.
     *
     * @param   ResourceInterface   $resource   resource to listen
     * @param   callable            $callback   callback to call on event
     * @param   integer             $eventsMask event types to listen
     */
    public function __construct(ResourceInterface $resource, $callback, $eventsMask)
    {
        if (!is_callable($callback)) {
            throw new \InvalidArgumentException(
                'EventListener\'s second argument should be callable.'
            );
        }

        $this->resource     = $resource;
        $this->callback     = $callback;
        $this->eventsMask   = $eventsMask;
    }

    /**
     * Returns listening resource.
     *
     * @return  ResourceInterface
     */
    public function getResource()
    {
        return $this->resource;
    }

    /**
     * Returns callback.
     *
     * @return  callable
     */
    public function getCallback()
    {
        return $this->callback;
    }

    /**
     * Checks whether listener supports provided resource event.
     *
     * @param   Event   $event
     */
    public function supports(Event $event)
    {
        return 0 !== ($this->eventsMask & $event->getType());
    }
}
