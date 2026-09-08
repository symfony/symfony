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
 * Ties together the messages of one flow, whatever the buses and transports they travel through.
 *
 * Add it to the message that opens the flow, with the identifier of the request or of the
 * process it belongs to. Every message dispatched while that one is handled inherits it.
 */
final class CorrelationStamp implements PropagatedStampInterface
{
    public function __construct(
        private string $id,
    ) {
    }

    public function getId(): string
    {
        return $this->id;
    }
}
