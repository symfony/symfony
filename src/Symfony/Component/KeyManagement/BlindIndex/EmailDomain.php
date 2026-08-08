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
 * Indexes the domain of an email address rather than the address itself.
 *
 * Kept in a second column next to {@see Email}, it answers "every account of that company"
 * without the database holding a single address. It is also the shape every partial search takes
 * here: rather than making the index searchable by pieces, name the question and index the answer.
 *
 * It takes an address or a bare domain alike, because both paths need it: a row is indexed from
 * the address it carries, and the query has only the domain to go on. The domain is folded, which
 * RFC 5321 allows because it is the case-insensitive half of an address.
 *
 * It leaks more than the address index does, and knowingly so. A domain has far fewer distinct
 * values than an address, so the counts per tag say a great deal, and the largest bucket is
 * usually guessable outright.
 *
 * @author Florent Morselli <florent.morselli@spomky-labs.com>
 *
 * @experimental
 */
final class EmailDomain extends BlindIndex
{
    protected function project(#[\SensitiveParameter] string $value): string
    {
        $value = trim($value);
        $at = strrpos($value, '@');

        return mb_strtolower(false === $at ? $value : substr($value, $at + 1));
    }
}
