<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Amqp\Exception;

use Interop\Amqp\AmqpMessage;
use Symfony\Component\Amqp\RetryStrategy\RetryStrategyInterface;

/**
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Grégoire Pineau <lyrixx@lyrixx.info>
 */
class NonRetryableException extends \RuntimeException implements ExceptionInterface
{
    /**
     * @var RetryStrategyInterface
     */
    private $retryStrategy;

    /**
     * @var AmqpMessage
     */
    private $amqpMessage;

    public function __construct(RetryStrategyInterface $retryStrategy, AmqpMessage $amqpMessage)
    {
        parent::__construct(sprintf('The message has been retried too many times (%s).', $amqpMessage->getHeader('retries')));

        $this->retryStrategy = $retryStrategy;
        $this->amqpMessage = $amqpMessage;
    }

    public function getRetryStrategy()
    {
        return $this->retryStrategy;
    }

    /**
     * @return AmqpMessage
     */
    public function getAmqpMessage()
    {
        return $this->amqpMessage;
    }
}
