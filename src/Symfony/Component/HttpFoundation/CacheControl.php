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
 * CacheControl is a container for HTTP cache instructions.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
final class CacheControl
{
    /**
     * @var array<string, string|bool>
     */
    private array $directives = [];

    public function __construct(array $directives = [])
    {
        $this->directives = $directives;
    }

    public static function fromHeader(string $header): self
    {
        return new self(self::parseCacheControl($header));
    }

    public function empty(): bool
    {
        return 0 === \count($this->directives);
    }

    public function all(): array
    {
        return $this->directives;
    }

    /**
     * Add or replace many Cache-Control directives.
     *
     * @param array<string, bool|string> $directives
     */
    public function addCacheControlDirectives(array $directives): void
    {
        foreach ($directives as $key => $value) {
            $this->addCacheControlDirective($key, $value);
        }
    }

    /**
     * Add or replace a Cache-Control directive.
     */
    public function addCacheControlDirective(string $key, bool|string $value = true): void
    {
        $this->directives[$key] = $value;
    }

    /**
     * Returns true if the Cache-Control directive is defined.
     */
    public function hasCacheControlDirective(string $key): bool
    {
        return \array_key_exists($key, $this->directives);
    }

    /**
     * Returns a Cache-Control directive value by name.
     */
    public function getCacheControlDirective(string $key): bool|string|null
    {
        return $this->directives[$key] ?? null;
    }

    /**
     * Removes a Cache-Control directive.
     */
    public function removeCacheControlDirective(string $key): void
    {
        unset($this->directives[$key]);
    }

    public function getCacheControlHeader(): string
    {
        ksort($this->directives);

        return HeaderUtils::toString($this->directives, ',');
    }

    /**
     * Parses a Cache-Control HTTP header.
     */
    public static function parseCacheControl(string $header): array
    {
        $parts = HeaderUtils::split($header, ',=');

        return HeaderUtils::combine($parts);
    }
}
