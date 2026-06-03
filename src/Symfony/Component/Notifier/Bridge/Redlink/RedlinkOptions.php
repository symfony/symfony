<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\Redlink;

use Symfony\Component\Notifier\Message\MessageOptionsInterface;

/**
 * @author Mateusz Żyła <https://github.com/plotkabytes>
 */
final class RedlinkOptions implements MessageOptionsInterface
{
    public function __construct(
        private array $options = [],
    ) {
    }

    public function toArray(): array
    {
        return array_filter($this->options);
    }

    public function getRecipientId(): ?string
    {
        return $this->options['externalId'] ?? null;
    }

    /**
     * @return $this
     */
    public function validity(int $validity): static
    {
        $this->options['validity'] = $validity;

        return $this;
    }

    /**
     * @return $this
     */
    public function scheduleTime(int $scheduleTime): static
    {
        $this->options['scheduleTime'] = $scheduleTime;

        return $this;
    }

    /**
     * @return $this
     */
    public function type(int $type): static
    {
        $this->options['type'] = $type;

        return $this;
    }

    /**
     * @return $this
     */
    public function shortLink(bool $shortLink): static
    {
        $this->options['shortLink'] = $shortLink;

        return $this;
    }

    /**
     * @return $this
     */
    public function webhookUrl(string $webhookUrl): static
    {
        $this->options['webhookUrl'] = $webhookUrl;

        return $this;
    }

    /**
     * @return $this
     */
    public function externalId(string $externalId): static
    {
        $this->options['externalId'] = $externalId;

        return $this;
    }
}
