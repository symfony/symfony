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
    public function accountId(string $accountId): static
    {
        $this->options['accountId'] = $accountId;

        return $this;
    }

    /**
     * @return $this
     */
    public function applicationId(string $applicationId): static
    {
        $this->options['applicationId'] = $applicationId;

        return $this;
    }

    /**
     * @return $this
     */
    public function expiration(string $expiration): static
    {
        $this->options['expiration'] = $expiration;

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
    public function priority(string $priority): static
    {
        $this->options['priority'] = $priority;

        return $this;
    }

    /**
     * @return $this
     */
    public function tag(string $tag): static
    {
        $this->options['tag'] = $tag;

        return $this;
    }

    public function toArray(): array
    {
        return $this->options;
    }
}
