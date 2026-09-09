<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\KeyManagement;

use Symfony\Component\KeyManagement\Exception\LogicException;

/**
 * Keeps key material out of the object that owns it, so that printing the object cannot print the
 * key.
 *
 * A key held in a property is printed by everything that walks an object: `var_dump()`,
 * `print_r()`, `var_export()`, `serialize()`, and the `VarCloner` behind `dump()`, the profiler and
 * the exception page. One dump of a service, or one exception rendered in debug, is enough to put a
 * key in a log or on a screen. Hiding it per tool does not work either, since only `var_dump()`
 * honours `__debugInfo()` and nothing at all intercepts `print_r()` or `var_export()`.
 *
 * So the material is not a property: it lives in a holder that a `WeakMap` keyed by the object
 * points at. The object then has nothing to print, the holder is reachable from nowhere else, and
 * it dies with the object it belongs to. Serialization is refused outright rather than silently
 * producing an object whose key is gone.
 *
 * `sodium_memzero()` still zeroes the holder's property in place, which is what makes wiping work
 * the same as it did when the key was a property of the object.
 *
 * @author Florent Morselli <florent.morselli@spomky-labs.com>
 *
 * @internal
 */
trait KeyMaterial
{
    /**
     * @var \WeakMap<object, KeyMaterialHolder>|null
     */
    private static ?\WeakMap $material = null;

    public function __serialize(): array
    {
        throw new LogicException(\sprintf('An instance of "%s" cannot be serialized: it holds key material, which is deliberately kept out of the object.', static::class));
    }

    /**
     * @param string|array<string, string> $material
     */
    private function keepMaterial(#[\SensitiveParameter] string|array $material): void
    {
        self::$material ??= new \WeakMap();
        self::$material[$this] = new KeyMaterialHolder($material);
    }

    private function hasMaterial(): bool
    {
        return isset(self::$material[$this]);
    }

    /**
     * @return string|array<string, string>
     */
    private function material(): string|array
    {
        $material = (self::$material[$this] ?? null)?->material;

        return $material ?? throw new LogicException(\sprintf('The key material of "%s" has already been wiped.', static::class));
    }

    /**
     * Zeroes the material where it lies, then drops the holder.
     *
     * PHP strings are reference-counted with copy-on-write, so `sodium_memzero()` only reaches the
     * buffer while the holder is the sole owner: a consumer that kept a copy of the key keeps it,
     * and there is no primitive that would take it back.
     */
    private function wipeMaterial(): void
    {
        if (!isset(self::$material[$this])) {
            return;
        }

        $holder = self::$material[$this];
        if (\is_string($holder->material) && \function_exists('sodium_memzero')) {
            sodium_memzero($holder->material);
        }

        unset(self::$material[$this]);
    }
}
