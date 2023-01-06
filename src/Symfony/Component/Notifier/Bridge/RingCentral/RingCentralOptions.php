<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\RingCentral;

use Symfony\Component\Notifier\Message\MessageOptionsInterface;

/**
 * @author gnito-org <https://github.com/gnito-org>
 */
final class RingCentralOptions implements MessageOptionsInterface
{
    private array $options;

    public function __construct(array $options = [])
    {
        $this->options = $options;
    }

    public function getCountryCallingCode(): ?string
    {
        return $this->options['country_calling_code'] ?? null;
    }

    public function getCountryId(): ?string
    {
        return $this->options['country_id'] ?? null;
    }

    public function getCountryIsoCode(): ?string
    {
        return $this->options['country_iso_code'] ?? null;
    }

    public function getCountryName(): ?string
    {
        return $this->options['country_name'] ?? null;
    }

    public function getCountryUri(): ?string
    {
        return $this->options['country_uri'] ?? null;
    }

    public function getFrom(): ?string
    {
        return $this->options['from'] ?? null;
    }

    public function getRecipientId(): ?string
    {
        return $this->options['recipient_id'] ?? null;
    }

    public function setCountryCallingCode(string $countryCallingCode): self
    {
        $this->options['country_calling_code'] = $countryCallingCode;

        return $this;
    }

    public function setCountryId(string $countryId): self
    {
        $this->options['country_id'] = $countryId;

        return $this;
    }

    public function setCountryIsoCode(string $countryIsoCode): self
    {
        $this->options['country_iso_code'] = $countryIsoCode;

        return $this;
    }

    public function setCountryName(string $countryName): self
    {
        $this->options['country_name'] = $countryName;

        return $this;
    }

    public function setCountryUri(string $countryUri): self
    {
        $this->options['country_uri'] = $countryUri;

        return $this;
    }

    public function setFrom(string $from): self
    {
        $this->options['from'] = $from;

        return $this;
    }

    public function setRecipientId(string $id): self
    {
        $this->options['recipient_id'] = $id;

        return $this;
    }

    public function toArray(): array
    {
        return $this->options;
    }
}
