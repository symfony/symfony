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

use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\NoHandlerForMessageException;

/**
 * @author Samuel Roze <samuel.roze@gmail.com>
 */
interface HandlerLocatorInterface
{
    /**
     * Returns the handler for the given message.
     *
     * @throws NoHandlerForMessageException When no handler is found
     */
    public function getHandler(Envelope $envelope): callable;
}
