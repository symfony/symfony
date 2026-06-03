<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Core\Authentication\RememberMe;

/**
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 *
 * @internal
 */
final class PersistentToken implements PersistentTokenInterface
{
    private \DateTimeImmutable $lastUsed;

    public function __construct(
        private string $userIdentifier,
        private string $series,
        #[\SensitiveParameter] private string $tokenValue,
        \DateTimeInterface $lastUsed,
    ) {
        if ('' === $userIdentifier) {
            throw new \InvalidArgumentException('$userIdentifier must not be empty.');
        }
        if (!$series) {
            throw new \InvalidArgumentException('$series must not be empty.');
        }
        if (!$tokenValue) {
            throw new \InvalidArgumentException('$tokenValue must not be empty.');
        }

        $this->lastUsed = \DateTimeImmutable::createFromInterface($lastUsed);
    }

    public function getUserIdentifier(): string
    {
        return $this->userIdentifier;
    }

    public function getSeries(): string
    {
        return $this->series;
    }

    public function getTokenValue(): string
    {
        return $this->tokenValue;
    }

    public function getLastUsed(): \DateTime
    {
        return \DateTime::createFromImmutable($this->lastUsed);
    }
}
