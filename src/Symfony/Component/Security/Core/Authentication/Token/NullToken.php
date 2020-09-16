<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Core\Authentication\Token;

/**
 * @author Wouter de Jong <wouter@wouterj.nl>
 */
class NullToken implements TokenInterface
{
    public function __toString(): string
    {
        return '';
    }

    public function getRoleNames(): array
    {
        return [];
    }

    public function getCredentials()
    {
        return '';
    }

    public function getUser()
    {
        return null;
    }

    public function setUser($user)
    {
        throw new \BadMethodCallException('Cannot set user on a NullToken.');
    }

    public function getUsername()
    {
        return '';
    }

    public function isAuthenticated()
    {
        return true;
    }

    public function setAuthenticated(bool $isAuthenticated)
    {
        throw new \BadMethodCallException('Cannot change authentication state of NullToken.');
    }

    public function eraseCredentials()
    {
    }

    public function getAttributes()
    {
        return [];
    }

    public function setAttributes(array $attributes)
    {
        throw new \BadMethodCallException('Cannot set attributes of NullToken.');
    }

    public function hasAttribute(string $name)
    {
        return false;
    }

    public function getAttribute(string $name)
    {
        return null;
    }

    public function setAttribute(string $name, $value)
    {
        throw new \BadMethodCallException('Cannot add attribute to NullToken.');
    }

    public function __serialize(): array
    {
        return [];
    }

    public function __unserialize(array $data): void
    {
    }

    public function serialize()
    {
        return '';
    }

    public function unserialize($serialized)
    {
    }
}
