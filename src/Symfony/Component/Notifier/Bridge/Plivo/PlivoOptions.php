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
    private array $options;

    public function __construct(array $options = [])
    {
        $this->options = $options;
    }

    public function getLog(): ?bool
    {
        return $this->options['log'] ?? null;
    }

    public function getMediaUrls(): ?string
    {
        return $this->options['media_urls'] ?? null;
    }

    public function getMethod(): ?string
    {
        return $this->options['method'] ?? null;
    }

    public function getPowerpackUuid(): ?string
    {
        return $this->options['powerpack_uuid'] ?? null;
    }

    public function getRecipientId(): ?string
    {
        return $this->options['recipient_id'] ?? null;
    }

    public function getSrc(): ?string
    {
        return $this->options['src'] ?? null;
    }

    public function getTrackable(): ?bool
    {
        return $this->options['trackable'] ?? null;
    }

    public function getType(): ?string
    {
        return $this->options['type'] ?? null;
    }

    public function getUrl(): ?string
    {
        return $this->options['url'] ?? null;
    }

    public function setLog(bool $log): self
    {
        $this->options['log'] = $log;

        return $this;
    }

    public function setMediaUrls(string $mediaUrls): self
    {
        $this->options['media_urls'] = $mediaUrls;

        return $this;
    }

    public function setMethod(string $method): self
    {
        $this->options['method'] = $method;

        return $this;
    }

    public function setPowerpackUuid(string $powerpackUuid): self
    {
        $this->options['powerpack_uuid'] = $powerpackUuid;

        return $this;
    }

    public function setRecipientId(string $id): self
    {
        $this->options['recipient_id'] = $id;

        return $this;
    }

    public function setSrc(string $src): self
    {
        $this->options['src'] = $src;

        return $this;
    }

    public function setTrackable(bool $trackable): self
    {
        $this->options['trackable'] = $trackable;

        return $this;
    }

    public function setType(string $type): self
    {
        $this->options['type'] = $type;

        return $this;
    }

    public function setUrl(string $url): self
    {
        $this->options['url'] = $url;

        return $this;
    }

    public function toArray(): array
    {
        return $this->options;
    }
}
