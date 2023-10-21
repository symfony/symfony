<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\Zulip;

use Symfony\Component\Notifier\Message\MessageOptionsInterface;

/**
 * @author Mohammad Emran Hasan <phpfour@gmail.com>
 */
final class ZulipOptions implements MessageOptionsInterface
{
    private ?string $topic;
    private ?string $recipient;

    public function __construct(string $topic = null, string $recipient = null)
    {
        $this->topic = $topic;
        $this->recipient = $recipient;
    }

    public function toArray(): array
    {
        return [
            'topic' => $this->topic,
            'recipient' => $this->recipient,
        ];
    }

    public function getRecipientId(): ?string
    {
        return $this->recipient;
    }

    /**
     * @return $this
     */
    public function topic(string $topic): static
    {
        $this->topic = $topic;

        return $this;
    }
}
