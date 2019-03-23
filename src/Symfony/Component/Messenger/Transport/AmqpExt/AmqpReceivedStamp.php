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

use Symfony\Component\Messenger\Stamp\StampInterface;

/**
 * Stamp applied when a message is received from Amqp.
 *
 * @experimental in 4.3
 */
class AmqpReceivedStamp implements StampInterface
{
    private $amqpEnvelope;

    public function __construct(\AMQPEnvelope $amqpEnvelope)
    {
        $this->amqpEnvelope = $amqpEnvelope;
    }

    public function getAmqpEnvelope(): \AMQPEnvelope
    {
        return $this->amqpEnvelope;
    }
}
