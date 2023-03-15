<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\Termii;

use Symfony\Component\Notifier\Message\MessageOptionsInterface;

/**
 * @author gnito-org <https://github.com/gnito-org>
 */
final class TermiiOptions implements MessageOptionsInterface
{
    private array $options;

    public function __construct(array $options = [])
    {
        $this->options = $options;
    }

    public function getChannel(): ?string
    {
        return $this->options['channel'] ?? null;
    }

    public function getFrom(): ?string
    {
        return $this->options['from'] ?? null;
    }

    public function getMediaCaption(): ?string
    {
        return $this->options['media_caption'] ?? null;
    }

    public function getMediaUrl(): ?string
    {
        return $this->options['media_url'] ?? null;
    }

    public function getRecipientId(): ?string
    {
        return $this->options['recipient_id'] ?? null;
    }

    public function getType(): ?string
    {
        return $this->options['type'] ?? null;
    }

    public function setChannel(string $channel): self
    {
        $this->options['channel'] = $channel;

        return $this;
    }

    public function setFrom(string $from): self
    {
        $this->options['from'] = $from;

        return $this;
    }

    public function setMediaCaption(string $mediaCaption): self
    {
        $this->options['media_caption'] = $mediaCaption;

        return $this;
    }

    public function setMediaUrl(string $mediaUrl): self
    {
        $this->options['media_url'] = $mediaUrl;

        return $this;
    }

    public function setRecipientId(string $id): self
    {
        $this->options['recipient_id'] = $id;

        return $this;
    }

    public function setType(string $type): self
    {
        $this->options['type'] = $type;

        return $this;
    }

    public function toArray(): array
    {
        return $this->options;
    }
}
