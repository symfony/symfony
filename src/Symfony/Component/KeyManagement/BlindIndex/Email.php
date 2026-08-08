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

use Symfony\Component\KeyManagement\BlindIndex;

/**
 * Indexes an email address, so that a row can be found by one the database does not hold.
 *
 * Only the domain is folded, which is what RFC 5321 says: it is case-insensitive, while the local
 * part is left to the receiving server and is therefore case-sensitive as far as anyone else is
 * concerned. So `Ada@Example.org` and `Ada@example.ORG` share a tag, and `ada@example.org` does
 * not get one of its own.
 *
 * Mind what follows from that. Most providers do treat the local part as case-insensitive in
 * practice, so an application that lets someone register as `Ada@example.org` and sign in as
 * `ada@example.org` will not find the row. The fix is not here: fold the address once, where it
 * enters the application, and store the folded form. An application that would rather have the
 * index do it subclasses {@see BlindIndex} with a projection of its own, and knowingly indexes
 * two addresses the standard considers distinct as one.
 *
 * @author Florent Morselli <florent.morselli@spomky-labs.com>
 *
 * @experimental
 */
final class Email extends BlindIndex
{
    protected function project(#[\SensitiveParameter] string $value): string
    {
        $value = trim($value);
        $at = strrpos($value, '@');

        return false === $at ? $value : substr($value, 0, $at + 1).mb_strtolower(substr($value, $at + 1));
    }
}
