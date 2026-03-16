<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpClient\Cookie;

use Symfony\Contracts\HttpClient\Cookie\CookieInterface;
use Symfony\Contracts\HttpClient\Cookie\CookieStoreInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * An immutable store of HTTP request cookies.
 *
 * @author Edouard Courty <edouard.courty2@gmail.com>
 */
final class CookieStore implements CookieStoreInterface
{
    /** @var array<string, CookieInterface> */
    private readonly array $cookies;

    /**
     * @param iterable<CookieInterface> $cookies
     */
    public function __construct(iterable $cookies = [])
    {
        $indexed = [];
        foreach ($cookies as $cookie) {
            $indexed[$cookie->getName()] = $cookie;
        }
        $this->cookies = $indexed;
    }

    /**
     * Creates a CookieStore by parsing a Cookie header string.
     *
     * Accepts the format used in the Cookie HTTP header: "name1=value1; name2=value2".
     */
    public static function fromString(string $cookies): self
    {
        if ('' === trim($cookies)) {
            return new self();
        }

        $objects = [];
        foreach (explode(';', $cookies) as $pair) {
            if ('' !== trim($pair)) {
                $objects[] = Cookie::fromString($pair);
            }
        }

        return new self($objects);
    }

    /**
     * Creates a CookieStore from an associative or indexed iterable.
     *
     * Accepted formats:
     *   - ['name' => 'value', ...]           (associative array)
     *   - ['name=value', ...]                (indexed strings)
     *   - [new Cookie('name', 'value'), ...]  (Cookie objects)
     *   - any Traversable yielding the above
     */
    public static function fromArray(iterable $cookies): self
    {
        $objects = [];
        foreach ($cookies as $name => $value) {
            $objects[] = match (true) {
                $value instanceof Cookie => $value,
                \is_int($name) => Cookie::fromString((string) $value),
                default => new Cookie($name, (string) $value),
            };
        }

        return new self($objects);
    }

    /**
     * Builds a CookieStore from the Set-Cookie headers of an HTTP response.
     *
     * Only the name=value pair is extracted; cookie attributes (Path, Domain,
     * Secure, HttpOnly, SameSite, Expires) are intentionally ignored since this
     * store is meant for outgoing request cookies.
     *
     * @param bool $throw Whether an exception should be thrown on 3/4/5xx status codes
     */
    public static function extractFromResponse(ResponseInterface $response, bool $throw = true): self
    {
        $cookies = [];
        foreach ($response->getHeaders($throw)['set-cookie'] ?? [] as $header) {
            // Each Set-Cookie header is "name=value; attr1; attr2=v; ..."
            // We pass only the first "name=value" segment to fromString().
            // Last header for a given name wins (standard browser semantics).
            // Non-RFC-compliant cookies from the server are silently skipped.
            try {
                $cookies = array_merge($cookies, self::fromString(explode(';', $header, 2)[0])->toArray());
            } catch (\InvalidArgumentException) {
                // skip non-RFC-compliant Set-Cookie values
            }
        }

        return self::fromArray($cookies);
    }

    /**
     * Returns a new instance with the given cookie added or replaced.
     */
    public function withCookie(string|CookieInterface $name, string $value = ''): static
    {
        $cookie = $name instanceof CookieInterface ? $name : new Cookie($name, $value);

        $cookies = array_values($this->cookies);
        $cookies[] = $cookie;

        return new self($cookies);
    }

    public function hasCookie(string $name): bool
    {
        return isset($this->cookies[$name]);
    }

    public function getCookie(string $name): ?CookieInterface
    {
        return $this->cookies[$name] ?? null;
    }

    /**
     * Returns a new instance without the given cookie.
     */
    public function withoutCookie(string $name): static
    {
        if (!$this->hasCookie($name)) {
            return $this;
        }

        $cookies = array_values(array_filter(
            $this->cookies,
            static fn (Cookie $cookie) => $cookie->getName() !== $name,
        ));

        return new self($cookies);
    }

    /**
     * Returns the cookies as an associative array ['name' => 'value'].
     *
     * @return array<string, string>
     */
    public function toArray(): array
    {
        $result = [];
        foreach ($this->cookies as $name => $cookie) {
            $result[$name] = $cookie->getValue();
        }

        return $result;
    }

    public function count(): int
    {
        return \count($this->cookies);
    }

    /**
     * Returns an iterator over the CookieInterface objects, keyed by cookie name.
     *
     * @return \ArrayIterator<string, CookieInterface>
     */
    public function getIterator(): \ArrayIterator
    {
        return new \ArrayIterator($this->cookies);
    }

    /**
     * Serializes the store as a valid Cookie header value: "name1=value1; name2=value2".
     */
    public function __toString(): string
    {
        return implode('; ', $this->cookies);
    }
}
