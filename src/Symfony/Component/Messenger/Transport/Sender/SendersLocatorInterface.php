<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Transport\Sender;

use Symfony\Component\Messenger\Envelope;

/**
 * Maps a message to a list of senders.
 *
 * @author Samuel Roze <samuel.roze@gmail.com>
 * @author Tobias Schultze <http://tobion.de>
 *
 * @experimental in 4.2
 */
interface SendersLocatorInterface
{
    /**
     * Gets the senders for the given message name.
     *
     * @param bool|null &$handle True after calling the method when the next middleware
     *                           should also get the message; false otherwise
     *
     * @return iterable|SenderInterface[] Indexed by sender alias if available
     */
    public function getSenders(Envelope $envelope, ?bool &$handle = false): iterable;
}
