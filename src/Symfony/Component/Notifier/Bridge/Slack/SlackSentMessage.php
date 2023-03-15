<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\Slack;

use Symfony\Component\Notifier\Message\ChatMessage;
use Symfony\Component\Notifier\Message\MessageInterface;
use Symfony\Component\Notifier\Message\SentMessage;

/**
 * @author Maxim Dovydenok <dovydenok.maxim@gmail.com>
 */
final class SlackSentMessage extends SentMessage
{
    private string $channelId;

    public function __construct(MessageInterface $original, string $transport, string $channelId, string $messageId)
    {
        parent::__construct($original, $transport);
        $this->channelId = $channelId;
        $this->setMessageId($messageId);
    }

    public function getChannelId(): string
    {
        return $this->channelId;
    }

    public function getUpdateMessage(string $subject, array $options = []): ChatMessage
    {
        return new ChatMessage($subject, new UpdateMessageSlackOptions($this->channelId, $this->getMessageId(), $options));
    }
}
