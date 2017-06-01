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
 * The EventListener is a callable object that traces itself.
 *
 * @author Roland Franssen <franssen.roland@gmail.com>
 */
class EventListener
{
    private $invoker;
    private $callingCount;

    public function __construct(callable $invoker)
    {
        $this->invoker = $invoker;
    }

    /**
     * Invoke the dispatched event.
     *
     * @param Event $event
     */
    public function __invoke(Event $event)
    {
        call_user_func($this->invoker, $event);
        ++$this->callingCount;
    }

    /**
     * Get the current calling count.
     *
     * @return int
     */
    public function getCallingCount()
    {
        return $this->callingCount;
    }
}
