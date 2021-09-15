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

    private $userIdentifier;
    private $expires;
    private $value;

    public function __construct(/*string*/ $userIdentifier, /*int*/ $expires, /*string*/ $value)
    {
        if (\is_string($expires)) {
            trigger_deprecation('symfony/security-http', '5.4', 'The $userFqcn argument of "%s" is deprecated.', __METHOD__);

            $userIdentifier = $expires;
            $expires = $value;
            $value = func_get_arg(3);
        }

        $this->userIdentifier = $userIdentifier;
        $this->expires = $expires;
        $this->value = $value;
    }

    public static function fromRawCookie(string $rawCookie): self
    {
        $cookieParts = explode(self::COOKIE_DELIMITER, base64_decode($rawCookie), 4);
        if (false === $cookieParts[0] = base64_decode($cookieParts[0], true)) {
            throw new AuthenticationException('The user identifier contains a character from outside the base64 alphabet.');
        }
        if (3 !== \count($cookieParts)) {
            throw new AuthenticationException('The cookie contains invalid data.');
        }

        return new static(...$cookieParts);
    }

    public static function fromPersistentToken(PersistentToken $persistentToken, int $expires): self
    {
        return new static($persistentToken->getUserIdentifier(), $expires, $persistentToken->getSeries().':'.$persistentToken->getTokenValue());
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
        return base64_encode(implode(self::COOKIE_DELIMITER, [base64_encode($this->userIdentifier), $this->expires, $this->value]));
    }
}
