<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Stamp;

/**
 * Makes a worker send the message it received to its senders instead of handling it.
 *
 * Transports that yield messages created in the same process, like the scheduler one, add this stamp.
 * It is not sendable, so that a message read from a queue cannot carry it.
 */
final class RedispatchStamp implements NonSendableStampInterface
{
}
