<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\MessageBird;

use Symfony\Component\Notifier\Message\MessageOptionsInterface;

/**
 * @author Evert Jan Hakvoort <evertjan@hakvoort.io>
 *
 * @see https://developers.messagebird.com/api/sms-messaging/#sms-api
 */
final class MessageBirdOptions implements MessageOptionsInterface
{
    private $options;

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
        return $this->options['recipients'] ?? null;
    }

    public function validity(int $validity): self
    {
        $this->options['validity'] = $validity;

        return $this;
    }

    public function reference(string $reference): self
    {
        $this->options['reference'] = $reference;

        return $this;
    }
}
