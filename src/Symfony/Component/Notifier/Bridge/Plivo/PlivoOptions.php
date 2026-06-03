<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\Plivo;

use Symfony\Component\Notifier\Message\MessageOptionsInterface;

/**
 * @author gnito-org <https://github.com/gnito-org>
 */
final class PlivoOptions implements MessageOptionsInterface
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
    public function log(bool $log): static
    {
        $this->options['log'] = $log;

        return $this;
    }

    /**
     * @return $this
     */
    public function mediaUrls(string $mediaUrls): static
    {
        $this->options['media_urls'] = $mediaUrls;

        return $this;
    }

    /**
     * @return $this
     */
    public function method(string $method): static
    {
        $this->options['method'] = $method;

        return $this;
    }

    /**
     * @return $this
     */
    public function powerpackUuid(string $powerpackUuid): static
    {
        $this->options['powerpack_uuid'] = $powerpackUuid;

        return $this;
    }

    /**
     * @return $this
     */
    public function trackable(bool $trackable): static
    {
        $this->options['trackable'] = $trackable;

        return $this;
    }

    /**
     * @return $this
     */
    public function type(string $type): static
    {
        $this->options['type'] = $type;

        return $this;
    }

    /**
     * @return $this
     */
    public function url(string $url): static
    {
        $this->options['url'] = $url;

        return $this;
    }

    public function toArray(): array
    {
        return $this->options;
    }
}
