<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\Messenger;

/**
 * @author Samuel Roze <samuel.roze@gmail.com>
 *
 * @experimental in 4.1
 */
interface MessageBusInterface
{
    /**
     * Dispatches the given message.
     *
     * The bus can return a value coming from handlers, but is not required to do so.
     *
     * @param object $message
     *
     * @return mixed
     */
    public function dispatch($message);
}
