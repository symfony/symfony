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
    private string $class;
    private string $userIdentifier;
    private string $series;
    private string $tokenValue;
    private \DateTimeImmutable $lastUsed;

    public function __construct(string $class, string $userIdentifier, string $series, #[\SensitiveParameter] string $tokenValue, \DateTimeInterface $lastUsed)
    {
        if (!$class) {
            throw new \InvalidArgumentException('$class must not be empty.');
        }
        if ('' === $userIdentifier) {
            throw new \InvalidArgumentException('$userIdentifier must not be empty.');
        }
        if (!$series) {
            throw new \InvalidArgumentException('$series must not be empty.');
        }
        if (!$tokenValue) {
            throw new \InvalidArgumentException('$tokenValue must not be empty.');
        }

        $this->class = $class;
        $this->userIdentifier = $userIdentifier;
        $this->series = $series;
        $this->tokenValue = $tokenValue;
        $this->lastUsed = \DateTimeImmutable::createFromInterface($lastUsed);
    }

    public function getClass(): string
    {
        return $this->class;
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
