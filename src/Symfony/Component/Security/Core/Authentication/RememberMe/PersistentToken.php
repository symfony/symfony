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
    private $class;
    private $username;
    private $series;
    private $tokenValue;
    private $lastUsed;

    public function __construct(string $class, string $username, string $series, string $tokenValue, \DateTime $lastUsed)
    {
        if (empty($class)) {
            throw new \InvalidArgumentException('$class must not be empty.');
        }
        if ('' === $username) {
            throw new \InvalidArgumentException('$username must not be empty.');
        }
        if (empty($series)) {
            throw new \InvalidArgumentException('$series must not be empty.');
        }
        if (empty($tokenValue)) {
            throw new \InvalidArgumentException('$tokenValue must not be empty.');
        }

        $this->class = $class;
        $this->username = $username;
        $this->series = $series;
        $this->tokenValue = $tokenValue;
        $this->lastUsed = $lastUsed;
    }

    /**
     * {@inheritdoc}
     */
    public function getClass(): string
    {
        return $this->class;
    }

    /**
     * {@inheritdoc}
     */
    public function getUsername(): string
    {
        return $this->username;
    }

    /**
     * {@inheritdoc}
     */
    public function getSeries(): string
    {
        return $this->series;
    }

    /**
     * {@inheritdoc}
     */
    public function getTokenValue(): string
    {
        return $this->tokenValue;
    }

    /**
     * {@inheritdoc}
     */
    public function getLastUsed(): \DateTime
    {
        return $this->lastUsed;
    }
}
