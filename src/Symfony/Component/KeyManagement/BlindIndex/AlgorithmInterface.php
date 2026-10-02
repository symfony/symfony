<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\KeyManagement\BlindIndex;

/**
 * The keyed function a {@see \Symfony\Component\KeyManagement\BlindIndex} derives its tags with.
 *
 * Implementations MUST be pseudo-random functions. A fast non-cryptographic hash is not one,
 * whatever it is seeded with, and using one here defeats the key entirely: without that property,
 * holding the table is enough to recover the key and then to test a guessed value against every
 * row, which is exactly what the key is there to prevent. They MUST also be collision-resistant,
 * since a collision makes a query return a row that does not match.
 *
 * Which implementation is in use is part of the stored format: the same value gives a different
 * tag under each, so changing it means reindexing. It is therefore chosen explicitly by the
 * application and never probed at runtime, or hosts that disagree about which extensions are
 * loaded would write indexes that only match on some of them.
 *
 * @author Florent Morselli <florent.morselli@spomky-labs.com>
 *
 * @experimental
 */
interface AlgorithmInterface
{
    /**
     * Width of a tag, in bytes, which is fixed so that the column holding one is.
     *
     * A tag is stored in a column sized once and never again. An implementation returning a wider
     * one would have its tags truncated by the database rather than refused, and a truncated tag
     * matches rows that do not hold the value, so the width is checked on every derivation rather
     * than trusted.
     */
    public const int TAG_BYTES = 32;

    /**
     * @param string $key 32 bytes of key material
     *
     * @return string The raw tag, which MUST be exactly {@see TAG_BYTES} bytes whatever the length of `$value`
     */
    public function tag(#[\SensitiveParameter] string $value, #[\SensitiveParameter] string $key): string;
}
