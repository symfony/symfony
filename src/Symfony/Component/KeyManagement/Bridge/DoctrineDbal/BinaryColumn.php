<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\KeyManagement\Bridge\DoctrineDbal;

/**
 * Reads a binary value as bytes, whichever shape it comes in.
 *
 * A `BINARY` or `BLOB` column comes back as a string on some platforms and as a stream on others,
 * and the blob types hand their value over as a stream on the way in too. Everything this bridge
 * stores is binary, so both the data key rows and the encrypted columns have to settle that before
 * they can parse anything. DBAL's own `BinaryType` does the same, for the same reason.
 *
 * @author Florent Morselli <florent.morselli@spomky-labs.com>
 *
 * @internal
 */
trait BinaryColumn
{
    private static function bytes(#[\SensitiveParameter] mixed $value): string
    {
        return \is_resource($value) ? (string) stream_get_contents($value) : (string) $value;
    }
}
