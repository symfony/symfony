<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\EventDispatcher\Debug;

use Symphony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * @deprecated since Symphony 4.1
 *
 * @author Fabien Potencier <fabien@symphony.com>
 */
interface TraceableEventDispatcherInterface extends EventDispatcherInterface
{
    /**
     * Gets the called listeners.
     *
     * @return array An array of called listeners
     */
    public function getCalledListeners();

    /**
     * Gets the not called listeners.
     *
     * @return array An array of not called listeners
     */
    public function getNotCalledListeners();

    /**
     * Resets the trace.
     */
    public function reset();
}
