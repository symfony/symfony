<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Failure;

use Symfony\Component\Debug\Exception\FlattenException;
use Symfony\Component\Messenger\Envelope;

/**
 * Wrapper around failed messages to be handled by failure transport.
 *
 * @author Ryan Weaver <ryan@symfonycasts.com>
 *
 * @experimental in 4.3
 */
class FailedMessage
{
    private const STRATEGY_RETRY = 'retry';
    private const STRATEGY_RESEND = 'resend';

    private $failedEnvelope;
    private $exceptionMessage;
    private $flattenException;
    private $failedAt;
    private $strategy = self::STRATEGY_RESEND;

    public function __construct(Envelope $failedEnvelope, string $exceptionMessage, FlattenException $flattenException = null)
    {
        $this->failedEnvelope = $failedEnvelope;
        $this->exceptionMessage = $exceptionMessage;
        $this->flattenException = $flattenException;
        $this->failedAt = new \DateTimeImmutable();
    }

    public function getFailedEnvelope(): Envelope
    {
        return $this->failedEnvelope;
    }

    public function getExceptionMessage(): string
    {
        return $this->exceptionMessage;
    }

    public function getFlattenException(): ?FlattenException
    {
        return $this->flattenException;
    }

    public function getFailedAt(): \DateTimeInterface
    {
        return $this->failedAt;
    }

    public function isStrategyRetry(): bool
    {
        return self::STRATEGY_RETRY === $this->strategy;
    }

    /**
     * Marks that this message should be retried immediately when handled.
     */
    public function setToRetryStrategy()
    {
        $this->strategy = self::STRATEGY_RETRY;
    }

    /**
     * Marks that this message should be resent to the original sender when handled.
     */
    public function setToResendStrategy()
    {
        $this->strategy = self::STRATEGY_RESEND;
    }
}
