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
 */
final class RocketChatOptions implements MessageOptionsInterface
{
    /** prefix with '@' for personal messages */
    private ?string $channel = null;

    /** @var mixed[] */
    private array $attachments;

    /**
     * @param string[] $attachments
     */
    public function __construct(array $attachments = [])
    {
        $this->attachments = $attachments;
    }

    public function toArray(): array
    {
        return [
            'attachments' => [$this->attachments],
        ];
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
