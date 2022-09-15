<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\RocketChat;

use Symfony\Component\Notifier\Message\MessageOptionsInterface;

/**
 * @author Jeroen Spee <https://github.com/Jeroeny>
 *
 * @see https://rocket.chat/docs/administrator-guides/integrations/
 * @see https://developer.rocket.chat/reference/api/rest-api/endpoints/core-endpoints/chat-endpoints/postmessage
 */
final class RocketChatOptions implements MessageOptionsInterface
{
    /** prefix with '@' for personal messages */
    private ?string $channel = null;

    /** @var string[]|string[][] */
    private array $attachments;

    /** @var string[] */
    private array $payload;

    /**
     * @param string[]|string[][] $attachments
     * @param string[]            $payload
     */
    public function __construct(array $attachments = [], array $payload = [])
    {
        $this->attachments = $attachments;
        $this->payload = $payload;
    }

    public function toArray(): array
    {
        if ($this->attachments) {
            return $this->payload + ['attachments' => array_is_list($this->attachments) ? $this->attachments : [$this->attachments]];
        }

        return $this->payload;
    }

    public function getRecipientId(): ?string
    {
        return $this->channel;
    }

    /**
     * @return $this
     */
    public function channel(string $channel): static
    {
        $this->channel = $channel;

        return $this;
    }
}
