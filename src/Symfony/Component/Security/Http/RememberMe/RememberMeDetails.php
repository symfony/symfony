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

    public function __construct(
        private string $userIdentifier,
        private int $expires,
        private string $value,
    ) {
    }

    public static function fromRawCookie(string $rawCookie): self
    {
        if (!str_contains($rawCookie, self::COOKIE_DELIMITER)) {
            $rawCookie = base64_decode($rawCookie);
        }

        $cookieParts = explode(self::COOKIE_DELIMITER, $rawCookie, 4);

        if (4 !== \count($cookieParts)) {
            throw new AuthenticationException('The cookie contains invalid data.');
        }

        if (false === $cookieParts[1] = base64_decode(strtr($cookieParts[1], '-_~', '+/='), true)) {
            throw new AuthenticationException('The user identifier contains a character from outside the base64 alphabet.');
        }

        unset($cookieParts[0]);

        return new static(...$cookieParts);
    }

    public static function fromPersistentToken(PersistentToken $token, int $expires): self
    {
        return new static($token->getUserIdentifier(), $expires, $token->getSeries().':'.$token->getTokenValue());
    }

    public function withValue(string $value): self
    {
        $details = clone $this;
        $details->value = $value;

        return $details;
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
        return implode(self::COOKIE_DELIMITER, ['', strtr(base64_encode($this->userIdentifier), '+/=', '-_~'), $this->expires, $this->value]);
    }
}
