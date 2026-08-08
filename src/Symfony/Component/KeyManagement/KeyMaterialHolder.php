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

/**
 * What a {@see KeyMaterial} weak map points at, and the only thing holding a key.
 *
 * A slot of its own rather than the key itself, because `sodium_memzero()` needs somewhere it can
 * write: a weak map hands its values back by value, so a key stored directly in one could never be
 * zeroed where it lies.
 *
 * @author Florent Morselli <florent.morselli@spomky-labs.com>
 *
 * @internal
 */
final class KeyMaterialHolder
{
    /**
     * Null once wiped: `sodium_memzero()` leaves the slot it zeroed at null rather than empty.
     *
     * @var string|array<string, string>|null
     */
    public string|array|null $material;

    /**
     * @param string|array<string, string> $material
     */
    public function __construct(
        #[\SensitiveParameter] string|array $material,
    ) {
        $this->material = $material;
    }
}
