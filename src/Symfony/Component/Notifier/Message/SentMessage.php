<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Message;

/**
 * @author Jérémy Romey <jeremy@free-agent.fr>
 */
class SentMessage
{
    private ?string $messageId = null;

    public function __construct(
        private MessageInterface $original,
        private string $transport,
    ) {
    }

    public function getOriginalMessage(): MessageInterface
    {
        return $this->original;
    }

    public function getTransport(): string
    {
        return $this->transport;
    }

    public function setMessageId(string $id): void
    {
        $this->messageId = $id;
    }

    public function getMessageId(): ?string
    {
        return $this->messageId;
    }
}
