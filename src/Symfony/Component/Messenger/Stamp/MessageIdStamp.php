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
 * Identifies the message itself, as opposed to TransportMessageIdStamp which identifies one delivery in one transport.
 *
 * The same message keeps the same id across retries, through the failure transport and when it is replayed.
 *
 * @see TransportMessageIdStamp
 */
final class MessageIdStamp implements StampInterface
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
