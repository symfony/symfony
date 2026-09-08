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
 * Marks a message stored in an outbox transport with the name of the transport it must be forwarded to.
 */
final class OutboxStamp implements StampInterface
{
    public function __construct(
        private string $transportName,
    ) {
    }

    public function getTransportName(): string
    {
        return $this->transportName;
    }
}
