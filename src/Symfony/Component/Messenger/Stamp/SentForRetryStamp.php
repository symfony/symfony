<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Stamp;

/**
 * Stamp identifying whether a failed message was sent for retry.
 */
final class SentForRetryStamp implements NonSendableStampInterface
{
    public function __construct(
        public readonly bool $isSent,
    ) {
    }
}
