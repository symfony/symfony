<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\AccessToken;

/**
 * @author Pierre Rineau <pierre.rineau@processus.org>
 */
class AccessToken implements AccessTokenInterface
{
    /**
     * Identifier for tokens that are not generated using credentials.
     */
    public const IN_MEMORY = 'in_memory';

    protected ?\DateTimeImmutable $expiresAt;
    protected ?bool $hasExpired = null;

    /**
     * @param string $id Identifier of credentials used for generating it
     */
    public function __construct(
        protected readonly string $value,
        protected readonly string $type = 'Bearer',
        protected readonly int $expiresIn = 600,
        protected readonly \DateTimeImmutable $issuedAt = new \DateTimeImmutable(),
        protected readonly string $id = self::IN_MEMORY,
    ) {
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getValue(): string
    {
        return $this->value;
    }

    public function getExpiresIn(): int
    {
        return $this->expiresIn;
    }

    public function getIssuedAt(): \DateTimeImmutable
    {
        return $this->issuedAt;
    }

    public function getExpiresAt(): \DateTimeImmutable
    {
        return $this->expiresAt ??= $this->issuedAt->add(new \DateInterval(sprintf('PT%dS', $this->expiresIn)));
    }

    public function hasExpired(): bool
    {
        return $this->hasExpired ??= $this->hasExpiredAt(new \DateTimeImmutable());
    }

    public function hasExpiredAt(\DateTimeInterface $date): bool
    {
        return $this->getExpiresAt() <= $date;
    }

    public function getCredentialsId(): string
    {
        return $this->id;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
