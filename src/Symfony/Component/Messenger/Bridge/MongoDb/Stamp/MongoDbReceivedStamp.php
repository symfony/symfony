<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Bridge\MongoDb\Stamp;

use Symfony\Component\Messenger\Stamp\NonSendableStampInterface;

/**
 * @author Alessandro Lai <alessandro.lai85@gmail.com>
 */
final class MongoDbReceivedStamp implements NonSendableStampInterface
{
    public function __construct(
        private string $id,
        private ?string $queueName = null,
    ) {
    }

    public function getId(): string
    {
        return $this->id;
    }

    /**
     * The queue the message was claimed from, which can differ from the
     * transport's own queue when a worker listens to several queues.
     */
    public function getQueueName(): ?string
    {
        return $this->queueName;
    }
}
