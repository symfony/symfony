<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Symfony\Component\Encryption\Certificate;

/**
 * The subject or issuer Distinguished Name of an X.509 certificate.
 *
 * @author David Gebler <me@davegebler.com>
 *
 * @api
 */
final class DistinguishedName
{
    /**
     * @param array<string, string> $fields RDN fields keyed by short name (CN, O, OU, C, ST, L, emailAddress, ...)
     */
    public function __construct(
        private readonly array $fields,
    ) {
    }

    public function commonName(): ?string
    {
        return $this->get('CN');
    }

    public function organization(): ?string
    {
        return $this->get('O');
    }

    public function organizationalUnit(): ?string
    {
        return $this->get('OU');
    }

    public function country(): ?string
    {
        return $this->get('C');
    }

    public function state(): ?string
    {
        return $this->get('ST');
    }

    public function locality(): ?string
    {
        return $this->get('L');
    }

    public function emailAddress(): ?string
    {
        return $this->get('emailAddress');
    }

    public function get(string $field): ?string
    {
        return $this->fields[$field] ?? null;
    }

    /**
     * @return array<string, string>
     */
    public function toArray(): array
    {
        return $this->fields;
    }

    public function equals(self $other): bool
    {
        return $this->fields === $other->fields;
    }
}
