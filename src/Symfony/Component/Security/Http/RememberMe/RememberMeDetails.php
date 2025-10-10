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

    private ?string $userFqcn = null;
    private string $userIdentifier;
    private int $expires;
    private string $value;

    /**
     * @param string $userIdentifier
     * @param int    $expires
     * @param string $value
     */
    public function __construct(
        $userIdentifier,
        $expires,
        $value,
    ) {
        if (\func_num_args() > 3) {
            if (\func_num_args() < 5 || func_get_arg(4)) {
                trigger_deprecation('symfony/security-http', '7.4', 'Passing a user FQCN to %s() is deprecated. The user class will be removed from the remember-me cookie in 8.0.', __CLASS__, __NAMESPACE__);
            }

            if (!\is_string($userIdentifier)) {
                throw new \TypeError(\sprintf('Argument 1 passed to "%s()" must be a string, "%s" given.', __METHOD__, get_debug_type($userIdentifier)));
            }

            $this->userFqcn = $userIdentifier;
            $userIdentifier = $expires;
            $expires = $value;

            if (\func_num_args() <= 3) {
                throw new \TypeError(\sprintf('Argument 4 passed to "%s()" must be a string, the argument is missing.', __METHOD__));
            }

            $value = func_get_arg(3);
        }

        if (!\is_string($userIdentifier)) {
            throw new \TypeError(\sprintf('The $userIdentifier argument passed to "%s()" must be a string, "%s" given.', __METHOD__, get_debug_type($userIdentifier)));
        }

        if (!\is_int($expires) && !preg_match('/^\d+$/', $expires)) {
            throw new \TypeError(\sprintf('$The $expires argument  passed to "%s()" must be an integer, "%s" given.', __METHOD__, get_debug_type($expires)));
        }

        if (!\is_string($value)) {
            throw new \TypeError(\sprintf('The $value argument  passed to "%s()" must be a string, "%s" given.', __METHOD__, get_debug_type($value)));
        }

        $this->userIdentifier = $userIdentifier;
        $this->expires = $expires;
        $this->value = $value;
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

        return new static(...self::collectConstructorArguments(strtr($cookieParts[0], '.', '\\'), $cookieParts[1], $cookieParts[2], $cookieParts[3]));
    }

    public static function fromPersistentToken(PersistentToken $token, int $expires): self
    {
        return new static(...self::collectConstructorArguments(method_exists($token, 'getClass') ? $token->getClass(false) : '', $token->getUserIdentifier(), $expires, $token->getSeries().':'.$token->getTokenValue()));
    }

    public function withValue(string $value): self
    {
        $details = clone $this;
        $details->value = $value;

        return $details;
    }

    /**
     * @deprecated since Symfony 7.4, the user FQCN will be removed from the remember-me cookie in 8.0
     */
    public function getUserFqcn(): string
    {
        trigger_deprecation('symfony/security-http', '7.4', 'The "%s()" method is deprecated: the user FQCN will be removed from the remember-me cookie in 8.0.', __METHOD__);

        return $this->userFqcn ?? '';
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
        return implode(self::COOKIE_DELIMITER, [strtr($this->userFqcn ?? '', '\\', '.'), strtr(base64_encode($this->userIdentifier), '+/=', '-_~'), $this->expires, $this->value]);
    }

    private static function collectConstructorArguments(string $userFqcn, string $userIdentifier, int $expires, string $value): array
    {
        $constructor = new \ReflectionMethod(static::class, '__construct');

        if (self::class === $constructor->class) {
            return [$userFqcn, $userIdentifier, $expires, $value, false];
        }

        if (3 < $constructor->getNumberOfRequiredParameters()) {
            trigger_deprecation('symfony/security-http', '7.4', 'Extending the "%s" class and overriding the constructor with four required arguments is deprecated. Change the constructor signature to __construct(string $userIdentifier, int $expires, string $value).', self::class);

            return [$userFqcn, $userIdentifier, $expires, $value];
        }

        return [$userIdentifier, $expires, $value];
    }
}
