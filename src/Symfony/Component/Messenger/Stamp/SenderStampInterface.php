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
 * A stamp that tells a sender how to send the message.
 *
 * Like any non-sendable stamp, it does not reach the receiving side, but an outbox stores it with the message so that the sender gets it when the message is forwarded.
 */
interface SenderStampInterface extends NonSendableStampInterface
{
}
