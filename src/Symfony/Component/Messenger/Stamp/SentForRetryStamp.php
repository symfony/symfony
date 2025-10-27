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
 * Stamp indicating whether a failed message has been sent for retry.
 */
final readonly class SentForRetryStamp implements NonSendableStampInterface
{
    public function __construct(
        public bool $isSent,
    ) {
    }
}
