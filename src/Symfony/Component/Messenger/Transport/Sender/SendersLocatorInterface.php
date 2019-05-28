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
use Symfony\Component\Messenger\Exception\UnknownSenderException;

/**
 * Maps a message to a list of senders.
 *
 * @author Samuel Roze <samuel.roze@gmail.com>
 * @author Tobias Schultze <http://tobion.de>
 *
 * @experimental in 4.3
 */
interface SendersLocatorInterface
{
    /**
     * Gets the senders for the given message name.
     *
     * @return iterable|SenderInterface[] Indexed by sender alias if available
     */
    public function getSenders(Envelope $envelope): iterable;

    /**
     * Returns a specific sender by its alias.
     *
     * @param string $alias The alias given to the sender in getSenders()
     *
     * @throws UnknownSenderException If the sender is not found
     */
    public function getSenderByAlias(string $alias): SenderInterface;
}
