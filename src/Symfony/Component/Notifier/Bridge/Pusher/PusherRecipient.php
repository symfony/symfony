<?php

declare(strict_types=1);

namespace Symfony\Component\Notifier\Bridge\Pusher;

use Symfony\Component\Notifier\Recipient\RecipientInterface;

class PusherRecipient implements RecipientInterface
{
    private array $channels;

    public function __construct(array $channels)
    {
        $this->channels = $channels;
    }

    public function getChannels(): array
    {
        return $this->channels;
    }
}
