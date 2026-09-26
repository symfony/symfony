<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\BrowserKit;

use Symfony\Component\BrowserKit\Exception\InvalidArgumentException;
use Symfony\Component\BrowserKit\Exception\UnexpectedValueException;

/**
 * Cookie represents an HTTP cookie.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class Cookie
{
    /**
     * Handles dates as defined by RFC 2616 section 3.3.1, and also some other
     * non-standard, but common formats.
     */
    private const DATE_FORMATS = [
        'D, d M Y H:i:s T',
        'D, d-M-y H:i:s T',
        'D, d-M-Y H:i:s T',
        'D, d-m-y H:i:s T',
        'D, d-m-Y H:i:s T',
        'D M j G:i:s Y',
        'D M d H:i:s Y T',
    ];

    protected string $value;
    protected ?string $expires = null;
    protected string $path;
    protected string $rawValue;
    private bool $browserCompatible = false;
    private ?int $maxAge = null;
    private bool $hostOnly = false;

    /**
     * Sets a cookie.
     *
     * @param string          $name         The cookie name
     * @param string|null     $value        The value of the cookie
     * @param string|int|null $expires      The time the cookie expires
     * @param string|null     $path         The path on the server in which the cookie will be available on
     * @param string          $domain       The domain that the cookie is available
     * @param bool            $secure       Indicates that the cookie should only be transmitted over a secure HTTPS connection from the client
     * @param bool            $httponly     The cookie httponly flag
     * @param bool            $encodedValue Whether the value is encoded or not
     * @param string|null     $samesite     The cookie samesite attribute
     * @param int|null        $maxAge       The maximum lifetime of the cookie in seconds
     * @param bool            $hostOnly     Whether the cookie is available only to the origin host
     */
    public function __construct(
        private string $name,
        ?string $value,
        string|int|null $expires = null,
        ?string $path = null,
        private string $domain = '',
        private bool $secure = false,
        private bool $httponly = true,
        bool $encodedValue = false,
        private ?string $samesite = null,
        ?int $maxAge = null,
        bool $hostOnly = false,
    ) {
        $this->maxAge = $maxAge;
        $this->hostOnly = $hostOnly;

        if ($encodedValue) {
            $this->rawValue = $value ?? '';
            $this->value = rawurldecode($this->rawValue);
        } else {
            $this->value = $value ?? '';
            $this->rawValue = rawurlencode($this->value);
        }
        $this->path = $path ?: '/';

        if (null !== $this->maxAge) {
            $now = time();
            $expires = 0 >= $this->maxAge ? $now - 1 : ($this->maxAge > \PHP_INT_MAX - $now ? \PHP_INT_MAX : $now + $this->maxAge);
        }

        if (null !== $expires) {
            $timestampAsDateTime = \DateTimeImmutable::createFromFormat('U', $expires);
            if (false === $timestampAsDateTime) {
                throw new UnexpectedValueException(\sprintf('The cookie expiration time "%s" is not valid.', $expires));
            }

            $this->expires = $timestampAsDateTime->format('U');
        }
    }

    /**
     * Returns the HTTP representation of the Cookie.
     */
    public function __toString(): string
    {
        $cookie = \sprintf('%s=%s', $this->name, $this->rawValue);

        if (null !== $this->expires) {
            $dateTime = \DateTimeImmutable::createFromFormat('U', $this->expires, new \DateTimeZone('GMT'));
            $cookie .= '; expires='.str_replace('+0000', '', $dateTime->format(self::DATE_FORMATS[0]));
        }

        if (null !== $this->maxAge) {
            $cookie .= '; max-age='.$this->maxAge;
        }

        if ('' !== $this->domain && !$this->hostOnly) {
            $cookie .= '; domain='.$this->domain;
        }

        if ($this->path) {
            $cookie .= '; path='.$this->path;
        }

        if ($this->secure) {
            $cookie .= '; secure';
        }

        if ($this->httponly) {
            $cookie .= '; httponly';
        }

        if (null !== $this->samesite) {
            $cookie .= '; samesite='.$this->samesite;
        }

        return $cookie;
    }

    /**
     * Creates a Cookie instance from a Set-Cookie header value.
     *
     * @throws InvalidArgumentException
     */
    public static function fromString(string $cookie, ?string $url = null): static
    {
        return self::createFromString($cookie, $url, false);
    }

    /**
     * Creates a Cookie instance using browser-compatible Set-Cookie semantics.
     *
     * @throws InvalidArgumentException
     */
    public static function fromStringBrowserCompatible(string $cookie, ?string $url = null): static
    {
        return self::createFromString($cookie, $url, true);
    }

    private static function parseDate(string $dateValue): ?string
    {
        // trim single quotes around date if present
        if (($length = \strlen($dateValue)) > 1 && "'" === $dateValue[0] && "'" === $dateValue[$length - 1]) {
            $dateValue = substr($dateValue, 1, -1);
        }

        foreach (self::DATE_FORMATS as $dateFormat) {
            if (false !== $date = \DateTimeImmutable::createFromFormat($dateFormat, $dateValue, new \DateTimeZone('GMT'))) {
                return $date->format('U');
            }
        }

        // attempt a fallback for unusual formatting
        if (false !== $date = date_create_immutable($dateValue, new \DateTimeZone('GMT'))) {
            return $date->format('U');
        }

        return null;
    }

    /**
     * Gets the name of the cookie.
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Gets the value of the cookie.
     */
    public function getValue(): string
    {
        return $this->value;
    }

    /**
     * Gets the raw value of the cookie.
     */
    public function getRawValue(): string
    {
        return $this->rawValue;
    }

    /**
     * Gets the expires time of the cookie.
     */
    public function getExpiresTime(): ?string
    {
        return $this->expires;
    }

    /**
     * Returns the maximum lifetime of the cookie in seconds.
     */
    public function getMaxAge(): ?int
    {
        return $this->maxAge;
    }

    /**
     * Gets the path of the cookie.
     */
    public function getPath(): string
    {
        return $this->path;
    }

    /**
     * Gets the domain of the cookie.
     */
    public function getDomain(): string
    {
        return $this->domain;
    }

    /**
     * Returns whether the cookie is available only to the origin host.
     */
    public function isHostOnly(): bool
    {
        return $this->hostOnly;
    }

    /**
     * Returns the secure flag of the cookie.
     */
    public function isSecure(): bool
    {
        return $this->secure;
    }

    /**
     * Returns the httponly flag of the cookie.
     */
    public function isHttpOnly(): bool
    {
        return $this->httponly;
    }

    /**
     * Returns true if the cookie has expired.
     */
    public function isExpired(): bool
    {
        if ($this->browserCompatible) {
            return null !== $this->expires && time() > $this->expires;
        }

        return null !== $this->expires && 0 != $this->expires && $this->expires <= time();
    }

    /**
     * Gets the samesite attribute of the cookie.
     */
    public function getSameSite(): ?string
    {
        return $this->samesite;
    }

    private static function createFromString(string $cookie, ?string $url, bool $browserCompatible): static
    {
        $parts = explode(';', $cookie);

        if (!str_contains($parts[0], '=')) {
            throw new InvalidArgumentException(\sprintf('The cookie string "%s" is not valid.', $parts[0]));
        }

        [$name, $value] = explode('=', array_shift($parts), 2);

        if ($browserCompatible) {
            $name = trim($name, " \t");
            $value = trim($value, " \t");
        } else {
            $name = trim($name);
            $value = trim($value);
        }

        $values = [
            'name' => $name,
            'value' => $value,
            'expires' => null,
            'path' => '/',
            'domain' => '',
            'secure' => false,
            'httponly' => false,
            'passedRawValue' => true,
            'samesite' => null,
            'max-age' => null,
            'hostOnly' => false,
        ];

        if (null !== $url) {
            if (false === ($urlParts = parse_url($url)) || !isset($urlParts['host'])) {
                throw new InvalidArgumentException(\sprintf('The URL "%s" is not valid.', $url));
            }

            $values['domain'] = $urlParts['host'];
            $values['path'] = isset($urlParts['path']) ? substr($urlParts['path'], 0, strrpos($urlParts['path'], '/')) : '';
        }

        $hasDomainAttribute = false;
        $hasPathAttribute = false;

        foreach ($parts as $part) {
            $part = $browserCompatible ? trim($part, " \t") : trim($part);

            if ('secure' === strtolower($part)) {
                // Ignore the secure flag if the original URI is not given or is not HTTPS
                if (!$browserCompatible && (null === $url || !isset($urlParts['scheme']) || 'https' !== $urlParts['scheme'])) {
                    continue;
                }

                $values['secure'] = true;

                continue;
            }

            if ('httponly' === strtolower($part)) {
                $values['httponly'] = true;

                continue;
            }

            if (2 === \count($elements = explode('=', $part, 2))) {
                $attribute = strtolower($browserCompatible ? trim($elements[0], " \t") : $elements[0]);
                if ($browserCompatible) {
                    $elements[1] = trim($elements[1], " \t");
                }
                if ($browserCompatible && ('secure' === $attribute || 'httponly' === $attribute)) {
                    if ($elements[1]) {
                        $values[$attribute] = true;
                    }

                    continue;
                }
                if ('expires' === $attribute) {
                    $elements[1] = $browserCompatible ? self::parseBrowserCompatibleExpires($elements[1]) : self::parseDate($elements[1]);
                } elseif ($browserCompatible && 'max-age' === $attribute) {
                    if (null === $maxAge = self::parseMaxAge($elements[1])) {
                        continue;
                    }

                    $elements[1] = $maxAge;
                }

                $hasDomainAttribute = $hasDomainAttribute || 'domain' === $attribute;
                $hasPathAttribute = $hasPathAttribute || 'path' === $attribute;
                $values[$attribute] = $elements[1];
            }
        }

        if ($browserCompatible) {
            if ($hasDomainAttribute) {
                $values['domain'] = strtolower(trim($values['domain'], " \t"));
                if ('' !== $values['domain'] && str_ends_with($values['domain'], '.') && '' !== trim($values['domain'], '.')) {
                    $values['domain'] = '';
                } elseif ('' !== $values['domain'] && '.' !== $values['domain'] && str_starts_with($values['domain'], '.')) {
                    $values['domain'] = substr($values['domain'], 1);
                }
            }

            if (!$hasDomainAttribute || '' === $values['domain']) {
                $values['hostOnly'] = true;
                $values['domain'] = $urlParts['host'] ?? '';
            }

            if (!$hasPathAttribute || !str_starts_with($values['path'], '/')) {
                $values['path'] = self::getDefaultPath($urlParts['path'] ?? '/');
            }
        }

        $arguments = [
            $values['name'],
            $values['value'],
            $values['expires'],
            $values['path'],
            $values['domain'],
            $values['secure'],
            $values['httponly'],
            $values['passedRawValue'],
            $values['samesite'],
        ];
        if ($browserCompatible) {
            $arguments[] = $values['max-age'];
            $arguments[] = $values['hostOnly'];
        }

        $cookie = new static(...$arguments);
        $cookie->browserCompatible = $browserCompatible;

        return $cookie;
    }

    private static function parseMaxAge(string $maxAge): ?int
    {
        if (!preg_match('/^[+-]?\d+$/D', $maxAge)) {
            return null;
        }

        return self::parseNumericInteger($maxAge);
    }

    private static function parseBrowserCompatibleExpires(string $expires): ?string
    {
        if (!is_numeric($expires)) {
            return self::parseDate($expires);
        }

        $expires = self::parseNumericInteger($expires);

        return null === $expires ? null : (string) $expires;
    }

    private static function parseNumericInteger(string $value): ?int
    {
        if (!preg_match('/^[+-]?\d+$/D', $value)) {
            $number = (float) $value;
            if (!is_finite($number) || $number < \PHP_INT_MIN || $number > \PHP_INT_MAX) {
                return null;
            }

            if (8 === \PHP_INT_SIZE && ($number <= (float) \PHP_INT_MIN || $number >= (float) \PHP_INT_MAX)) {
                return null;
            }

            return (int) $number;
        }

        $negative = str_starts_with($value, '-');
        $digits = ltrim($value, '+-');
        $digits = ltrim($digits, '0');
        $digits = '' === $digits ? '0' : $digits;
        $limit = $negative ? substr((string) \PHP_INT_MIN, 1) : (string) \PHP_INT_MAX;

        if (\strlen($digits) > \strlen($limit) || (\strlen($digits) === \strlen($limit) && 0 < strcmp($digits, $limit))) {
            return null;
        }

        return (int) ($negative ? '-'.$digits : $digits);
    }

    private static function getDefaultPath(string $path): string
    {
        if (!str_starts_with($path, '/') || '/' === $path || false === $lastSlash = strrpos($path, '/')) {
            return '/';
        }

        return 0 === $lastSlash ? '/' : substr($path, 0, $lastSlash);
    }
}
