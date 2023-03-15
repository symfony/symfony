<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\Bandwidth;

use Symfony\Component\Notifier\Message\MessageOptionsInterface;

/**
 * @author gnito-org <https://github.com/gnito-org>
 */
final class BandwidthOptions implements MessageOptionsInterface
{
    private array $options;

    public function __construct(array $options = [])
    {
        $this->options = $options;
    }

    public function getAccountId(): ?string
    {
        return $this->options['account_id'] ?? null;
    }

    public function getApplicationId(): ?string
    {
        return $this->options['application_id'] ?? null;
    }

    public function getExpiration(): ?string
    {
        return $this->options['expiration'] ?? null;
    }

    public function getFrom(): ?string
    {
        return $this->options['from'] ?? null;
    }

    public function getMedia(): ?array
    {
        return $this->options['media'] ?? null;
    }

    public function getPriority(): ?string
    {
        return $this->options['priority'] ?? null;
    }

    public function getRecipientId(): ?string
    {
        return $this->options['recipient_id'] ?? null;
    }

    public function getTag(): ?string
    {
        return $this->options['tag'] ?? null;
    }

    public function getTo(): ?array
    {
        return $this->options['to'] ?? null;
    }

    public function setAccountId(string $accountId): self
    {
        $this->options['account_id'] = $accountId;

        return $this;
    }

    public function setApplicationId(string $applicationId): self
    {
        $this->options['application_id'] = $applicationId;

        return $this;
    }

    public function setExpiration(string $expiration): self
    {
        $this->options['expiration'] = $expiration;

        return $this;
    }

    public function setFrom(string $from): self
    {
        $this->options['from'] = $from;

        return $this;
    }

    public function setMedia(array $media): self
    {
        $this->options['media'] = $media;

        return $this;
    }

    public function setPriority(string $priority): self
    {
        $this->options['priority'] = $priority;

        return $this;
    }

    public function setRecipientId(string $id): self
    {
        $this->options['recipient_id'] = $id;

        return $this;
    }

    public function setTag(string $tag): self
    {
        $this->options['tag'] = $tag;

        return $this;
    }

    public function setTo(array $to): self
    {
        $this->options['to'] = $to;

        return $this;
    }

    public function toArray(): array
    {
        return $this->options;
    }
}
