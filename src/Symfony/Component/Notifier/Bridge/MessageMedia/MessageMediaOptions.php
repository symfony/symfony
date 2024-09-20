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
    public function __construct(
        private array $options = [],
    ) {
    }

    public function getRecipientId(): ?string
    {
        return null;
    }

    /**
     * @return $this
     */
    public function callbackUrl(string $callbackUrl): static
    {
        $this->options['callback_url'] = $callbackUrl;

        return $this;
    }

    /**
     * @return $this
     */
    public function deliveryReport(bool $deliveryReport): static
    {
        $this->options['delivery_report'] = $deliveryReport;

        return $this;
    }

    /**
     * @return $this
     */
    public function format(string $format): static
    {
        $this->options['format'] = $format;

        return $this;
    }

    /**
     * @return $this
     */
    public function media(array $media): static
    {
        $this->options['media'] = $media;

        return $this;
    }

    /**
     * @return $this
     */
    public function expiry(int $expiry): static
    {
        $this->options['message_expiry_timestamp'] = $expiry;

        return $this;
    }

    /**
     * @return $this
     */
    public function metadata(array $metadata): static
    {
        $this->options['metadata'] = $metadata;

        return $this;
    }

    /**
     * @return $this
     */
    public function scheduled(string $scheduled): static
    {
        $this->options['scheduled'] = $scheduled;

        return $this;
    }

    /**
     * @return $this
     */
    public function subject(string $subject): static
    {
        $this->options['subject'] = $subject;

        return $this;
    }

    public function toArray(): array
    {
        return $this->options;
    }
}
