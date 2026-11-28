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
 * The outcome of AckAwareDispatcher::dispatch(), to report to the $onHandled callable of the execution strategy.
 *
 * @author Jonathan H. Wage
 */
final class DispatchResult
{
    public function __construct(
        public readonly Envelope $envelope,
        public readonly ?\Throwable $error,
    ) {
    }
}
