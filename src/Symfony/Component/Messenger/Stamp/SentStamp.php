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
 * Marker stamp identifying a message sent by the `SendMessageMiddleware`.
 *
 * @see \Symfony\Component\Messenger\Middleware\SendMessageMiddleware
 *
 * @author Maxime Steinhausser <maxime.steinhausser@gmail.com>
 */
final class SentStamp implements NonSendableStampInterface
{
    private string $senderClass;
    private ?string $senderAlias;

    public function __construct(string $senderClass, string $senderAlias = null)
    {
        $this->senderAlias = $senderAlias;
        $this->senderClass = $senderClass;
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
