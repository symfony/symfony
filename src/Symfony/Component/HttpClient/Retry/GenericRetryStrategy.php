<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpClient\Retry;

use Symfony\Component\HttpClient\Exception\InvalidArgumentException;
use Symfony\Component\HttpClient\Response\AsyncContext;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;

/**
 * Decides to retry the request when HTTP status codes belong to the given list of codes.
 *
 * @author Jérémy Derussé <jeremy@derusse.com>
 */
class GenericRetryStrategy implements RetryStrategyInterface
{
    public const IDEMPOTENT_METHODS = ['GET', 'HEAD', 'PUT', 'DELETE', 'OPTIONS', 'TRACE', 'QUERY'];
    public const DEFAULT_RETRY_STATUS_CODES = [
        0 => self::IDEMPOTENT_METHODS, // for transport exceptions
        423,
        425,
        429,
        500 => self::IDEMPOTENT_METHODS,
        502,
        503,
        504 => self::IDEMPOTENT_METHODS,
        507 => self::IDEMPOTENT_METHODS,
        510 => self::IDEMPOTENT_METHODS,
    ];

    /**
     * @param array $statusCodes List of HTTP status codes that trigger a retry
     * @param int   $delayMs     Amount of time to delay (or the initial value when multiplier is used)
     * @param float $multiplier  Multiplier to apply to the delay each time a retry occurs
     * @param int   $maxDelayMs  Maximum delay to allow (0 means no maximum)
     * @param float $jitter      Probability of randomness int delay (0 = none, 1 = 100% random)
     */
    public function __construct(
        private array $statusCodes = self::DEFAULT_RETRY_STATUS_CODES,
        private int $delayMs = 1000,
        private float $multiplier = 2.0,
        private int $maxDelayMs = 0,
        private float $jitter = 0.1,
    ) {
        if ($delayMs < 0) {
            throw new InvalidArgumentException(\sprintf('Delay must be greater than or equal to zero: "%s" given.', $delayMs));
        }

        if ($multiplier < 1) {
            throw new InvalidArgumentException(\sprintf('Multiplier must be greater than or equal to one: "%s" given.', $multiplier));
        }

        if ($maxDelayMs < 0) {
            throw new InvalidArgumentException(\sprintf('Max delay must be greater than or equal to zero: "%s" given.', $maxDelayMs));
        }

        if ($jitter < 0 || $jitter > 1) {
            throw new InvalidArgumentException(\sprintf('Jitter must be between 0 and 1: "%s" given.', $jitter));
        }
    }

    public function shouldRetry(AsyncContext $context, ?string $responseContent, ?TransportExceptionInterface $exception): ?bool
    {
        $statusCode = $context->getStatusCode();
        if (\in_array($statusCode, $this->statusCodes, true)) {
            return true;
        }
        if (isset($this->statusCodes[$statusCode]) && \is_array($this->statusCodes[$statusCode])) {
            return \in_array($context->getInfo('http_method'), $this->statusCodes[$statusCode], true);
        }
        if (null === $exception) {
            return false;
        }

        if (\in_array(0, $this->statusCodes, true)) {
            return true;
        }
        if (isset($this->statusCodes[0]) && \is_array($this->statusCodes[0])) {
            return \in_array($context->getInfo('http_method'), $this->statusCodes[0], true);
        }

        return false;
    }

    public function getDelay(AsyncContext $context, ?string $responseContent, ?TransportExceptionInterface $exception): int
    {
        $delay = $this->delayMs * $this->multiplier ** $context->getInfo('retry_count');

        if ($this->jitter > 0) {
            $randomness = (int) ($delay * $this->jitter);
            $delay += random_int(-$randomness, +$randomness);
        }

        if ($delay > $this->maxDelayMs && 0 !== $this->maxDelayMs) {
            return $this->maxDelayMs;
        }

        return (int) $delay;
    }
}
