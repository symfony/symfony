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
 * @experimental in 5.1
 *
 * @see https://rocket.chat/docs/administrator-guides/integrations/
 */
final class RocketChatOptions implements MessageOptionsInterface
{
    /** @var string|null prefix with '@' for personal messages */
    private $channel;

    /** @var mixed[] */
    private $attachments;

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
    public function channel(string $channel): self
    {
        $this->channel = $channel;

        return $this;
    }
}
