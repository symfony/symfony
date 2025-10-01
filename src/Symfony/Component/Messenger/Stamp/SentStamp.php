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

use Symfony\Component\Messenger\Middleware\SendMessageMiddleware;

/**
 * Marker stamp identifying a message sent by the `SendMessageMiddleware`.
 *
 * @see SendMessageMiddleware
 *
 * @author Maxime Steinhausser <maxime.steinhausser@gmail.com>
 */
final class SentStamp implements NonSendableStampInterface
{
    public function __construct(
        private string $senderClass,
        private ?string $senderAlias = null,
    ) {
    }

    public function getSenderClass(): string
    {
        return $this->senderClass;
    }

    public function getSenderAlias(): ?string
    {
        return $this->senderAlias;
    }
}
