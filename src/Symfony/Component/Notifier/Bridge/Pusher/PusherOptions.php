<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\Pusher;

use Symfony\Component\Notifier\Message\MessageOptionsInterface;

/**
 * @author Yasmany Cubela Medina <yasmanycm@gmail.com>
 */
final class PusherOptions implements MessageOptionsInterface
{
    public function __construct(
        private readonly array $channels,
    )
    {
    }

    public function toArray(): array
    {
        return $this->channels;
    }

    public function getRecipientId(): ?string
    {
        return null;
    }

    public function getChannels(): array
    {
        return $this->channels;
    }
}
