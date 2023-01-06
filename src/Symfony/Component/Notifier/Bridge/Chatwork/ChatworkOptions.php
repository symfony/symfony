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

use Symfony\Component\Notifier\Message\MessageOptionsInterface;

/**
 * @author Ippei Sumida <ippey.s@gmail.com>
 */
class ChatworkOptions implements MessageOptionsInterface
{
    private array $options;

    public function __construct(array $options = [])
    {
        $this->options = $options;
    }

    public function toArray(): array
    {
        return $this->options;
    }

    public function getRecipientId(): ?string
    {
        return '';
    }

    public function to(array|string $userIds): static
    {
        $this->options['to'] = $userIds;

        return $this;
    }

    public function selfUnread(bool $selfUnread): static
    {
        $this->options['selfUnread'] = $selfUnread;

        return $this;
    }
}
