<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Worker\MessageFetcher;

use Interop\Amqp\AmqpConsumer;
use Symfony\Component\Amqp\Broker;
use Symfony\Component\Worker\MessageCollection;

/**
 * @author Grégoire Pineau <lyrixx@lyrixx.info>
 */
class AmqpMessageFetcher implements MessageFetcherInterface
{
    private $broker;
    private $queueName;
    private $flags;

    public function __construct(Broker $broker, $queueName, $autoAck = false)
    {
        $this->broker = $broker;
        $this->queueName = $queueName;
        $this->flags = $autoAck ? AmqpConsumer::FLAG_NOACK : AmqpConsumer::FLAG_NOPARAM;
    }

    public function fetchMessages()
    {
        $msg = $this->broker->get($this->queueName, $this->flags);

        if (false === $msg) {
            return false;
        }

        return new MessageCollection($msg);
    }
}
