<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Execution\Message;

use Symfony\Component\Messenger\Envelope;

/**
 * @internal
 */
final class DeferredBatchMessage
{
    public bool $acked;

    public function __construct(
        public readonly string $transportName,
        public readonly Envelope $envelope,
        bool &$acked,
        public readonly float $queuedAt,
    ) {
        $this->acked = &$acked;
    }
}
