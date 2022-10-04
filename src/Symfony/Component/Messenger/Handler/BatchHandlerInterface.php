<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Handler;

/**
 * @author Nicolas Grekas <p@tchwork.com>
 */
interface BatchHandlerInterface
{
    /**
     * @param Acknowledger|null $ack The function to call to ack/nack the $message.
     *                               The message should be handled synchronously when null.
     *
     * @return mixed The number of pending messages in the batch if $ack is not null,
     *               the result from handling the message otherwise
     */
    // public function __invoke(object $message, Acknowledger $ack = null): mixed;

    /**
     * Flushes any pending buffers.
     *
     * @param bool $force Whether flushing is required; it can be skipped if not
     */
    public function flush(bool $force): void;
}
