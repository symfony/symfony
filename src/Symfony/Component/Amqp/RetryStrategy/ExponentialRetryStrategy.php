<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Amqp\RetryStrategy;

/**
 * The retry mechanism is based on a truncated exponential backoff algorithm.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Grégoire Pineau <lyrixx@lyrixx.info>
 */
class ExponentialRetryStrategy implements RetryStrategyInterface
{
    private $max;
    private $offset;

    /**
     * @param int $max    The maximum number of time to retry (0 means indefinitely)
     * @param int $offset The offset for the first power of 2
     */
    public function __construct($max = 0, $offset = 0)
    {
        $this->max = $max;
        $this->offset = $offset;
    }

    /**
     * {@inheritdoc}
     */
    public function isRetryable(\AMQPEnvelope $msg)
    {
        if (0 === $this->max) {
            return true;
        }

        $retries = (int) $msg->getHeader('retries');

        return $retries < $this->max;
    }

    /**
     * {@inheritdoc}
     */
    public function getWaitingTime(\AMQPEnvelope $msg)
    {
        $retries = (int) $msg->getHeader('retries');

        return pow(2, $retries + $this->offset);
    }
}
