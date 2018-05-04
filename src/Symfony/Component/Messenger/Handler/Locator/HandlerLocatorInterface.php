<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Handler\Locator;

use Symfony\Component\Messenger\Exception\NoHandlerForMessageException;

/**
 * @author Samuel Roze <samuel.roze@gmail.com>
 *
 * @experimental in 4.1
 */
interface HandlerLocatorInterface
{
    /**
     * Returns the handler for the given message.
     *
     * @param object $message
     *
     * @throws NoHandlerForMessageException
     *
     * @return callable
     */
    public function resolve($message): callable;
}
