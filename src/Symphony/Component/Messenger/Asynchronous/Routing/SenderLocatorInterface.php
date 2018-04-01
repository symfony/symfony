<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\Messenger\Asynchronous\Routing;

use Symphony\Component\Messenger\Transport\SenderInterface;

/**
 * @author Samuel Roze <samuel.roze@gmail.com>
 *
 * @experimental in 4.1
 */
interface SenderLocatorInterface
{
    /**
     * Gets the sender (if applicable) for the given message object.
     *
     * @param object $message
     *
     * @return SenderInterface[]
     */
    public function getSendersForMessage($message): array;
}
