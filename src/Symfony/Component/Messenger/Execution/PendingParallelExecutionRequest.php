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
final class PendingParallelExecutionRequest
{
    public bool $deferred = false;

    public function __construct(
        public readonly string $transportName,
        public readonly Envelope $envelope,
        public readonly int $channelId,
        public readonly string $affinityKey,
        public readonly bool $isProbe,
    ) {
    }
}
