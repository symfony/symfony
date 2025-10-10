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

    /**
     * @param array $info attaches any Transport-related information to the sent message
     */
    public function __construct(
        private MessageInterface $original,
        private string $transport,
        private array $info = [],
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

    /**
     * Returns extra info attached to the message.
     *
     * @param string|null $key if null, the whole info array will be returned, else returns the info value or null
     */
    public function getInfo(?string $key = null): mixed
    {
        if (null !== $key) {
            return $this->info[$key] ?? null;
        }

        return $this->info;
    }
}
