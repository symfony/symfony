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

use Symfony\Component\Messenger\Envelope;

/**
 * Some transports may have multiple queues. This interface is used to read from only some queues.
 *
 * @author David Buchmann <mail@davidbu.ch>
 */
interface QueueReceiverInterface extends ReceiverInterface
{
    /**
     * Get messages from the specified queue names instead of consuming from all queues.
     *
     * @param string[] $queueNames
     *
     * @return Envelope[]
     */
    public function getFromQueues(array $queueNames): iterable;
}
