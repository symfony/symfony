<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Asynchronous\Routing;

use Symfony\Component\Messenger\Transport\SenderInterface;

/**
 * @author Samuel Roze <samuel.roze@gmail.com>
 * @author Tobias Schultze <http://tobion.de>
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
     * @return SenderInterface|null
     */
    public function getSenderForMessage($message): ?SenderInterface;
}
