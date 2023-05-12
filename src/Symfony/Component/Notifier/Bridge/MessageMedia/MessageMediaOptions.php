<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\MessageMedia;

use Symfony\Component\Notifier\Message\MessageOptionsInterface;

/**
 * @author gnito-org <https://github.com/gnito-org>
 */
final class MessageMediaOptions implements MessageOptionsInterface
{
    private array $options;

    public function __construct(array $options = [])
    {
        $this->options = $options;
    }

    public function getCallbackUrl(): ?string
    {
        return $this->options['callback_url'] ?? null;
    }

    public function getDeliveryReport(): ?bool
    {
        return $this->options['delivery_report'] ?? null;
    }

    public function getFormat(): ?string
    {
        return $this->options['format'] ?? null;
    }

    public function getFrom(): ?string
    {
        return $this->options['from'] ?? null;
    }

    public function getMedia(): ?array
    {
        return $this->options['media'] ?? null;
    }

    public function getMessageExpiryTimestamp(): ?int
    {
        return $this->options['message_expiry_timestamp'] ?? null;
    }

    public function getMetadata(): ?array
    {
        return $this->options['metadata'] ?? null;
    }

    public function getRecipientId(): ?string
    {
        return $this->options['recipient_id'] ?? null;
    }

    public function getScheduled(): ?string
    {
        return $this->options['scheduled'] ?? null;
    }

    public function getSubject(): ?string
    {
        return $this->options['subject'] ?? null;
    }

    public function setCallbackUrl(string $callbackUrl): self
    {
        $this->options['callback_url'] = $callbackUrl;

        return $this;
    }

    public function setDeliveryReport(bool $deliveryReport): self
    {
        $this->options['delivery_report'] = $deliveryReport;

        return $this;
    }

    public function setFormat(string $format): self
    {
        $this->options['format'] = $format;

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

    public function setMessageExpiryTimestamp(int $messageExpiryTimestamp): self
    {
        $this->options['message_expiry_timestamp'] = $messageExpiryTimestamp;

        return $this;
    }

    public function setMetadata(array $metadata): self
    {
        $this->options['metadata'] = $metadata;

        return $this;
    }

    public function setRecipientId(string $id): self
    {
        $this->options['recipient_id'] = $id;

        return $this;
    }

    public function setScheduled(string $scheduled): self
    {
        $this->options['scheduled'] = $scheduled;

        return $this;
    }

    public function setSubject(string $subject): self
    {
        $this->options['subject'] = $subject;

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
