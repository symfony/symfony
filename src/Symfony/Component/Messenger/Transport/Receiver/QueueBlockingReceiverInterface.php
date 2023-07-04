<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Transport\Receiver;

/**
 * Some transports may have multiple queues. This interface is used to read from only some queues in blocking mode.
 *
 * @author Alexander Melikhov <amelihovv@ya.ru>
 */
interface QueueBlockingReceiverInterface extends BlockingReceiverInterface
{
    /**
     * Pull messages from the specified queue names instead of consuming from all queues.
     *
     * @param string[]                       $queueNames
     * @param callable(\Symfony\Component\Messenger\Envelope):?false $callback   if callback return false, then processing thread will be
     *                                                                           returned to PHP script
     */
    public function pullFromQueues(array $queueNames, callable $callback): void;
}
