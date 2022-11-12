<?php


declare(strict_types=1);

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
    private array $channels;

    public function __construct(array $channels)
    {
        $this->channels = $channels;
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
