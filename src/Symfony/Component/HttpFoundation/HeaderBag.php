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
 * HeaderBag is a container for HTTP headers.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @implements \IteratorAggregate<string, list<string|null>>
 */
class HeaderBag implements \IteratorAggregate, \Countable, \Stringable
{
    public const DEFAULT_CACHE_CONTROL_TARGET = '';
    protected const UPPER = '_ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    protected const LOWER = '-abcdefghijklmnopqrstuvwxyz';

    /**
     * @var array<string, list<string|null>>
     */
    protected array $headers = [];

    /**
     * Map of target to cache control instructions.
     *
     * @var array<string, CacheControl>
     */
    protected array $cacheControls = [];

    public function __construct(array $headers = [])
    {
        foreach ($headers as $key => $values) {
            $this->set($key, $values);
        }
    }

    /**
     * Returns the headers as a string.
     */
    public function __toString(): string
    {
        if (!$headers = $this->all()) {
            return '';
        }

        ksort($headers);
        $max = max(array_map('strlen', array_keys($headers))) + 1;
        $content = '';
        foreach ($headers as $name => $values) {
            $name = ucwords($name, '-');
            foreach ($values as $value) {
                $content .= \sprintf("%-{$max}s %s\r\n", $name.':', $value);
            }
        }

        return $content;
    }

    /**
     * Returns the headers.
     *
     * @param string|null $key The name of the headers to return or null to get them all
     *
     * @return ($key is null ? array<string, list<string|null>> : list<string|null>)
     */
    public function all(?string $key = null): array
    {
        if (null !== $key) {
            $uniqueKey = strtr($key, self::UPPER, self::LOWER);
            if (str_ends_with($uniqueKey, 'cache-control')) {
                return [$this->getCacheControl($this->extractCacheControlTarget($key))->getCacheControlHeader()];
            }

            return $this->headers[$uniqueKey] ?? [];
        }

        $headers = $this->headers;
        // edge case: what if extending class directly changed Cache-Control in the $headers array?
        foreach ($this->cacheControls as $target => $cacheControl) {
            $headers[self::DEFAULT_CACHE_CONTROL_TARGET === $target ? 'cache-control' : $target.'-cache-control'] = [$cacheControl->getCacheControlHeader()];
        }

        return $headers;
    }

    /**
     * Returns the parameter keys.
     *
     * @return string[]
     */
    public function keys(): array
    {
        return array_keys($this->all());
    }

    /**
     * Replaces the current HTTP headers by a new set.
     */
    public function replace(array $headers = []): void
    {
        $this->cacheControls = [];
        $this->headers = [];
        $this->add($headers);
    }

    /**
     * Adds new headers the current HTTP headers set.
     */
    public function add(array $headers): void
    {
        foreach ($headers as $key => $values) {
            $this->set($key, $values);
        }
    }

    /**
     * Returns the first header by name or the default one.
     */
    public function get(string $key, ?string $default = null): ?string
    {
        $headers = $this->all($key);

        if (!$headers) {
            return $default;
        }

        if (null === $headers[0]) {
            return null;
        }

        return $headers[0];
    }

    /**
     * Sets a header by name.
     *
     * @param string|string[]|null $values  The value or an array of values
     * @param bool                 $replace Whether to replace the actual value or not (true by default)
     */
    public function set(string $key, string|array|null $values, bool $replace = true): void
    {
        $uniqueKey = strtr($key, self::UPPER, self::LOWER);

        if (str_ends_with($uniqueKey, 'cache-control')) {
            $this->setCacheControlFromHeader($key, $values, $replace);

            return;
        }

        if (\is_array($values)) {
            $values = array_values($values);

            if (true === $replace || !isset($this->headers[$uniqueKey])) {
                $this->headers[$uniqueKey] = $values;
            } else {
                $this->headers[$uniqueKey] = array_merge($this->headers[$uniqueKey], $values);
            }
        } else {
            if (true === $replace || !isset($this->headers[$uniqueKey])) {
                $this->headers[$uniqueKey] = [$values];
            } else {
                $this->headers[$uniqueKey][] = $values;
            }
        }
    }

    private function setCacheControlFromHeader(string $key, string|array|null $values, bool $replace = true): void
    {
        if (\is_array($values)) {
            $values = implode(', ', $values);
        }
        $target = $this->extractCacheControlTarget($key);
        if ($replace) {
            $this->cacheControls[$target] = CacheControl::fromHeader($values);

            return;
        }
        $this->getCacheControl($target)->addCacheControlDirectives(CacheControl::parseCacheControl($values));
    }

    /**
     * Returns true if the HTTP header is defined.
     */
    public function has(string $key): bool
    {
        return \array_key_exists(strtr($key, self::UPPER, self::LOWER), $this->all());
    }

    /**
     * Returns true if the given HTTP header contains the given value.
     */
    public function contains(string $key, string $value): bool
    {
        return \in_array($value, $this->all($key), true);
    }

    /**
     * Removes a header.
     */
    public function remove(string $key): void
    {
        $uniqueKey = strtr($key, self::UPPER, self::LOWER);

        unset($this->headers[$uniqueKey]);

        if (str_ends_with($uniqueKey, 'cache-control')) {
            $this->removeCacheControl($this->extractCacheControlTarget($key));
        }
    }

    /**
     * Returns the HTTP header value converted to a date.
     *
     * @throws \RuntimeException When the HTTP header is not parseable
     */
    public function getDate(string $key, ?\DateTimeInterface $default = null): ?\DateTimeImmutable
    {
        if (null === $value = $this->get($key)) {
            return null !== $default ? \DateTimeImmutable::createFromInterface($default) : null;
        }

        if (false === $date = \DateTimeImmutable::createFromFormat(\DATE_RFC2822, $value)) {
            throw new \RuntimeException(\sprintf('The "%s" HTTP header is not parseable (%s).', $key, $value));
        }

        return $date;
    }

    /**
     * Get the default or a targeted cache control instruction set.
     *
     * If the set did not exist yet, it is created.
     */
    public function getCacheControl(string $target = self::DEFAULT_CACHE_CONTROL_TARGET): CacheControl
    {
        // TODO do we need to lowercase the targets as well, and track the desired case as we do for the headers in ResponseHeaderBag
        if (!\array_key_exists($target, $this->cacheControls)) {
            $this->cacheControls[$target] = new CacheControl();
        }

        return $this->cacheControls[$target];
    }

    /**
     * Remove cache control settings.
     */
    public function removeCacheControl(string $target = self::DEFAULT_CACHE_CONTROL_TARGET): void
    {
        unset($this->cacheControls[$target]);
    }

    /**
     * Adds a custom Cache-Control directive.
     *
     * @deprecated Use getCacheControl()->addCacheControlDirective instead
     */
    public function addCacheControlDirective(string $key, bool|string $value = true): void
    {
        $this->getCacheControl()->addCacheControlDirective($key, $value);
    }

    /**
     * Returns true if the Cache-Control directive is defined.
     *
     * @deprecated Use getCacheControl()->hasCacheControlDirective instead
     */
    public function hasCacheControlDirective(string $key): bool
    {
        return $this->getCacheControl()->hasCacheControlDirective($key);
    }

    /**
     * Returns a Cache-Control directive value by name.
     *
     * @deprecated Use getCacheControl()->getCacheControlDirective instead
     */
    public function getCacheControlDirective(string $key): bool|string|null
    {
        return $this->getCacheControl()->getCacheControlDirective($key);
    }

    /**
     * Removes a Cache-Control directive.
     */
    public function removeCacheControlDirective(string $key): void
    {
        $this->getCacheControl()->removeCacheControlDirective($key);
    }

    /**
     * Returns an iterator for headers.
     *
     * @return \ArrayIterator<string, list<string|null>>
     */
    public function getIterator(): \ArrayIterator
    {
        return new \ArrayIterator($this->headers);
    }

    /**
     * Returns the number of headers.
     */
    public function count(): int
    {
        return \count($this->headers);
    }

    /**
     * @deprecated Use getCacheControl()->getCacheControlHeader instead
     */
    protected function getCacheControlHeader(): string
    {
        return $this->getCacheControl()->getCacheControlHeader();
    }

    /**
     * Parses a Cache-Control HTTP header.
     *
     * @deprecated Use CacheControl::fromHeader instead
     */
    protected function parseCacheControl(string $header): array
    {
        $parts = HeaderUtils::split($header, ',=');

        return HeaderUtils::combine($parts);
    }

    /**
     * Get the cache-control target from the header name.
     */
    private function extractCacheControlTarget(string $headerName): string
    {
        if ('cache-control' === strtolower($headerName)) {
            return self::DEFAULT_CACHE_CONTROL_TARGET;
        }

        return substr($headerName, 0, -\strlen('-cache-control'));
    }
}
