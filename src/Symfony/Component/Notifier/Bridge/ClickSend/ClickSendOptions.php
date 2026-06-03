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
    public function country(string $country): static
    {
        $this->options['country'] = $country;

        return $this;
    }

    /**
     * @return $this
     */
    public function customString(string $customString): static
    {
        $this->options['custom_string'] = $customString;

        return $this;
    }

    /**
     * @return $this
     */
    public function fromEmail(string $fromEmail): static
    {
        $this->options['from_email'] = $fromEmail;

        return $this;
    }

    /**
     * @return $this
     */
    public function listId(string $listId): static
    {
        $this->options['list_id'] = $listId;

        return $this;
    }

    /**
     * @return $this
     */
    public function schedule(int $schedule): static
    {
        $this->options['schedule'] = $schedule;

        return $this;
    }

    /**
     * @return $this
     */
    public function source(string $source): static
    {
        $this->options['source'] = $source;

        return $this;
    }

    public function toArray(): array
    {
        return $this->options;
    }
}
