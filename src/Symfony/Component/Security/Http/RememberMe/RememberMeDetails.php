<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Http\RememberMe;

use Symfony\Component\Security\Core\Authentication\RememberMe\PersistentToken;
use Symfony\Component\Security\Core\Exception\AuthenticationException;

/**
 * @author Wouter de Jong <wouter@wouterj.nl>
 */
class RememberMeDetails
{
    public const COOKIE_DELIMITER = ':';

    private $userFqcn;
    private $userIdentifier;
    private $expires;
    private $value;

    public function __construct(string $userFqcn, string $userIdentifier, int $expires, string $value)
    {
        $this->userFqcn = $userFqcn;
        $this->userIdentifier = $userIdentifier;
        $this->expires = $expires;
        $this->value = $value;
    }

    public static function fromRawCookie(string $rawCookie): self
    {
        $cookieParts = explode(self::COOKIE_DELIMITER, base64_decode($rawCookie), 4);
        if (false === $cookieParts[1] = base64_decode($cookieParts[1], true)) {
            throw new AuthenticationException('The user identifier contains a character from outside the base64 alphabet.');
        }
        if (4 !== \count($cookieParts)) {
            throw new AuthenticationException('The cookie contains invalid data.');
        }

        return new static(...$cookieParts);
    }

    public static function fromPersistentToken(PersistentToken $persistentToken, int $expires): self
    {
        return new static($persistentToken->getClass(), $persistentToken->getUserIdentifier(), $expires, $persistentToken->getSeries().':'.$persistentToken->getTokenValue());
    }

    public function withValue(string $value): self
    {
        $details = clone $this;
        $details->value = $value;

        return $details;
    }

    public function getUserFqcn(): string
    {
        return $this->userFqcn;
    }

    public function getUserIdentifier(): string
    {
        return $this->userIdentifier;
    }

    public function getExpires(): int
    {
        return $this->expires;
    }

    public function getValue(): string
    {
        return $this->value;
    }

    public function toString(): string
    {
        // $userIdentifier is encoded because it might contain COOKIE_DELIMITER, we assume other values don't
        return base64_encode(implode(self::COOKIE_DELIMITER, [$this->userFqcn, base64_encode($this->userIdentifier), $this->expires, $this->value]));
    }
}
