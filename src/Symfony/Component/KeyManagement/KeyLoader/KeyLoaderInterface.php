<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\KeyManagement\KeyLoader;

use Symfony\Component\KeyManagement\Exception\KeyNotFoundException;
use Symfony\Component\KeyManagement\Exception\RuntimeException;

/**
 * Sources raw key material by id for the local symmetric KMS backends
 * ({@see \Symfony\Component\KeyManagement\Local\SodiumKms},
 * {@see \Symfony\Component\KeyManagement\Local\OpenSslKms}).
 *
 * Implementations decide where keys live (in-memory map, filesystem, secret
 * store, ...) and when to read them. The contract is intentionally narrow:
 * given an id, return the raw bytes or throw.
 *
 * @author Florent Morselli <florent.morselli@spomky-labs.com>
 *
 * @experimental
 */
interface KeyLoaderInterface
{
    /**
     * @throws KeyNotFoundException If `$keyId` is not known to the loader
     * @throws RuntimeException     On a backend failure (I/O, parse, ...)
     */
    public function load(string $keyId): string;
}
