<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Execution;

use Symfony\Component\Messenger\Envelope;

/**
 * @internal
 */
final class MessageExecutionCallback
{
    public function __construct(
        private readonly \Closure $onHandled,
        private readonly \Closure $onAcknowledge,
    ) {
    }

    public function __invoke(Envelope $envelope, string $transportName, bool &$acked, ?\Throwable $e = null): void
    {
        ($this->onHandled)($envelope, $transportName, $acked, $e);
    }

    public function acknowledge(string $transportName, Envelope $envelope, ?\Throwable $e = null, ?\Closure $combine = null): void
    {
        ($this->onAcknowledge)($transportName, $envelope, $e, $combine);
    }
}
