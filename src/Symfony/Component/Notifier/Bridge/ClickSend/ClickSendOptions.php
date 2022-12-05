<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\ClickSend;

use Symfony\Component\Notifier\Message\MessageOptionsInterface;

/**
 * @author gnito-org <https://github.com/gnito-org>
 */
final class ClickSendOptions implements MessageOptionsInterface
{
    private array $options;

    public function __construct(array $options = [])
    {
        $this->options = $options;
    }

    public function getCountry(): ?string
    {
        return $this->options['country'] ?? null;
    }

    public function getCustomString(): ?string
    {
        return $this->options['custom_string'] ?? null;
    }

    public function getFrom(): ?string
    {
        return $this->options['from'] ?? null;
    }

    public function getFromEmail(): ?string
    {
        return $this->options['from_email'] ?? null;
    }

    public function getListId(): ?string
    {
        return $this->options['list_id'] ?? null;
    }

    public function getRecipientId(): ?string
    {
        return $this->options['recipient_id'] ?? null;
    }

    public function getSchedule(): ?int
    {
        return $this->options['schedule'] ?? null;
    }

    public function getSource(): ?string
    {
        return $this->options['source'] ?? null;
    }

    public function setCountry(string $country): self
    {
        $this->options['country'] = $country;

        return $this;
    }

    public function setCustomString(string $customString): self
    {
        $this->options['custom_string'] = $customString;

        return $this;
    }

    public function setFrom(string $from): self
    {
        $this->options['from'] = $from;

        return $this;
    }

    public function setFromEmail(string $fromEmail): self
    {
        $this->options['from_email'] = $fromEmail;

        return $this;
    }

    public function setListId(string $listId): self
    {
        $this->options['list_id'] = $listId;

        return $this;
    }

    public function setRecipientId(string $id): self
    {
        $this->options['recipient_id'] = $id;

        return $this;
    }

    public function setSchedule(int $schedule): self
    {
        $this->options['schedule'] = $schedule;

        return $this;
    }

    public function setSource(string $source): self
    {
        $this->options['source'] = $source;

        return $this;
    }

    public function toArray(): array
    {
        $options = $this->options;
        if (isset($options['recipient_id'])) {
            unset($options['recipient_id']);
        }

        return $options;
    }
}
