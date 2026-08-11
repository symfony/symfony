<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpFoundation;

/**
 * Cache directives addressed to one cache, as defined by RFC 9213.
 *
 * A cache that knows itself as "CDN" reads "CDN-Cache-Control" and ignores
 * "Cache-Control"; every other cache does the opposite. That makes it possible
 * to keep a response for hours in a CDN while telling browsers not to store it.
 *
 * There is no "s-maxage" counterpart to setMaxAge(): the target already names
 * the cache the directives are for, and "max-age" is the only one every cache
 * implementing RFC 9213 is required to honour.
 *
 * @see https://www.rfc-editor.org/rfc/rfc9213.html
 *
 * @author David Buchmann <mail@davidbu.ch>
 */
final class TargetedCacheControl
{
    /**
     * RFC 9213 section 2: the target is a token, matched case-insensitively.
     */
    private const TARGET_REGEX = '/^[!#$%&\'*+.^_`|~0-9A-Za-z-]+$/D';

    private readonly string $header;

    /**
     * @param string $target The cache the directives are addressed to, e.g. "CDN"
     *
     * @throws \InvalidArgumentException When the target is not a valid token
     */
    public function __construct(
        private readonly ResponseHeaderBag $headers,
        string $target,
    ) {
        if (!preg_match(self::TARGET_REGEX, $target)) {
            throw new \InvalidArgumentException(\sprintf('The cache-control target "%s" is not a valid token.', $target));
        }

        $this->header = $target.'-Cache-Control';
    }

    public function setMaxAge(int $value): static
    {
        return $this->set('max-age', (string) $value);
    }

    public function setStaleWhileRevalidate(int $value): static
    {
        return $this->set('stale-while-revalidate', (string) $value);
    }

    public function setStaleIfError(int $value): static
    {
        return $this->set('stale-if-error', (string) $value);
    }

    public function setPublic(): static
    {
        return $this->set('public')->remove('private');
    }

    public function setPrivate(): static
    {
        return $this->set('private')->remove('public');
    }

    public function setImmutable(bool $immutable = true): static
    {
        return $immutable ? $this->set('immutable') : $this->remove('immutable');
    }

    public function setNoStore(bool $noStore = true): static
    {
        return $noStore ? $this->set('no-store') : $this->remove('no-store');
    }

    /**
     * Adds or replaces a directive, for the ones this class does not model.
     *
     * Passing false removes the directive.
     */
    public function set(string $directive, bool|string $value = true): static
    {
        if (false === $value) {
            return $this->remove($directive);
        }

        $directives = $this->all();
        $directives[strtolower($directive)] = $value;

        return $this->write($directives);
    }

    public function remove(string $directive): static
    {
        $directives = $this->all();
        unset($directives[strtolower($directive)]);

        return $this->write($directives);
    }

    public function has(string $directive): bool
    {
        return \array_key_exists(strtolower($directive), $this->all());
    }

    public function get(string $directive): bool|string|null
    {
        return $this->all()[strtolower($directive)] ?? null;
    }

    /**
     * @return array<string, bool|string>
     */
    public function all(): array
    {
        if (!$header = $this->headers->all($this->header)) {
            return [];
        }

        return HeaderUtils::combine(HeaderUtils::split(implode(', ', $header), ',='));
    }

    /**
     * @param array<string, bool|string> $directives
     */
    private function write(array $directives): static
    {
        if (!$directives) {
            $this->headers->remove($this->header);

            return $this;
        }

        ksort($directives);
        $this->headers->set($this->header, HeaderUtils::toString($directives, ','));

        return $this;
    }
}
