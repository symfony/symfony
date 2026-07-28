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
final class DispatchEnvelopeMessage
{
    public function __construct(
        public readonly int $requestId,
        public readonly Envelope $envelope,
    ) {
    }
}
