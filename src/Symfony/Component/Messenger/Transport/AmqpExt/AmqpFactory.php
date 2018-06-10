<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Transport\AmqpExt;

class AmqpFactory
{
    public function createConnection(array $credentials): \AMQPConnection
    {
        return new \AMQPConnection($credentials);
    }

    public function createChannel(\AMQPConnection $connection): \AMQPChannel
    {
        return new \AMQPChannel($connection);
    }

    public function createQueue(\AMQPChannel $channel): \AMQPQueue
    {
        return new \AMQPQueue($channel);
    }

    public function createExchange(\AMQPChannel $channel): \AMQPExchange
    {
        return new \AMQPExchange($channel);
    }
}
