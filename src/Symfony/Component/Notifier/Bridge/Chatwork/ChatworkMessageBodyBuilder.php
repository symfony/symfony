<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\Chatwork;

/**
 * @author Ippei Sumida <ippey.s@gmail.com>
 */
class ChatworkMessageBodyBuilder
{
    private array $to = [];
    private string $body = '';
    private bool $selfUnread = false;

    public function to(array|string $userIds): self
    {
        if (\is_array($userIds)) {
            $this->to = $userIds;
        } else {
            $this->to = [$userIds];
        }

        return $this;
    }

    public function body(string $body): self
    {
        $this->body = $body;

        return $this;
    }

    public function selfUnread(bool $selfUnread): self
    {
        $this->selfUnread = $selfUnread;

        return $this;
    }

    public function getMessageBody(): array
    {
        $content = '';
        foreach ($this->to as $to) {
            $content .= sprintf("[To:%s]\n", $to);
        }
        $content .= $this->body;

        return [
            'body' => $content,
            'self_unread' => $this->selfUnread,
        ];
    }
}
