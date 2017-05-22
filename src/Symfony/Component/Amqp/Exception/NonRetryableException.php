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

use Symfony\Component\Amqp\RetryStrategy\RetryStrategyInterface;

/**
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Grégoire Pineau <lyrixx@lyrixx.info>
 */
class NonRetryableException extends \RuntimeException implements ExceptionInterface
{
    private $retryStrategy;
    private $envelope;

    public function __construct(RetryStrategyInterface $retryStrategy, \AMQPEnvelope $envelope)
    {
        parent::__construct(sprintf('The message has been retried too many times (%s).', $envelope->getHeader('retries')));

        $this->retryStrategy = $retryStrategy;
        $this->envelope = $envelope;
    }

    public function getRetryStrategy()
    {
        return $this->retryStrategy;
    }

    public function getEnvelope()
    {
        return $this->envelope;
    }
}
