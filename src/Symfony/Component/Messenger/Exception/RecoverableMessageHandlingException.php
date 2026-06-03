<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Exception;

/**
 * A concrete implementation of RecoverableExceptionInterface that can be used directly.
 *
 * @author Frederic Bouchery <frederic@bouchery.fr>
 */
class RecoverableMessageHandlingException extends RuntimeException implements RecoverableExceptionInterface
{
    /**
     * @param bool $forceRetry When true (the default, preserving 8.0 semantics), the message will be retried regardless
     *                         of the configured max_retries on the transport, which can cause unbounded retries. Pass
     *                         false to let the configured retry strategy bound the number of attempts.
     */
    public function __construct(
        string $message = '',
        int $code = 0,
        ?\Throwable $previous = null,
        private readonly ?int $retryDelay = null,
        private readonly bool $forceRetry = true,
    ) {
        parent::__construct($message, $code, $previous);
    }

    public function getRetryDelay(): ?int
    {
        return $this->retryDelay;
    }

    public function forceRetry(): bool
    {
        return $this->forceRetry;
    }
}
