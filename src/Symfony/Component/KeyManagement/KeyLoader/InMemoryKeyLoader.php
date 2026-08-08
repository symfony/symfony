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
use Symfony\Component\KeyManagement\KeyMaterial;

/**
 * Resolves keys from an associative array passed at construction time.
 *
 * The keys are master keys, and they are held out of the object rather than in a property, so that
 * no dump of the loader, nor of anything holding it, can print them, see {@see KeyMaterial}.
 *
 * @author Florent Morselli <florent.morselli@spomky-labs.com>
 *
 * @experimental
 */
final class InMemoryKeyLoader implements KeyLoaderInterface
{
    use KeyMaterial;

    /**
     * @param array<string, string> $keys map of keyId to raw key material
     */
    public function __construct(
        #[\SensitiveParameter] array $keys,
    ) {
        $this->keepMaterial($keys);
    }

    public function load(string $keyId): string
    {
        return $this->material()[$keyId] ?? throw new KeyNotFoundException($keyId);
    }
}
