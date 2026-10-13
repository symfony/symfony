<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\KeyManagement\BlindIndex\Projection;

use Symfony\Component\KeyManagement\BlindIndex\ProjectionInterface;

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
 * Folding means converting the domain to its ASCII form, as UTS #46 describes, rather than only
 * lowercasing it: `café.example` written with a precomposed `é`, with an `e` followed by a
 * combining accent, in uppercase or as `xn--caf-dma.example` share a tag. A domain that is not a
 * valid internationalized one, say with an underscore in it, is lowercased instead.
 *
 * It leaks more than the address index does, and knowingly so. A domain has far fewer distinct
 * values than an address, so the counts per tag say a great deal, and the largest bucket is
 * usually guessable outright.
 *
 * @author Florent Morselli <florent.morselli@spomky-labs.com>
 *
 * @experimental
 */
final class EmailDomain implements ProjectionInterface
{
    public function project(#[\SensitiveParameter] string $value): string
    {
        $value = trim($value);
        $at = strrpos($value, '@');

        return self::fold(false === $at ? $value : substr($value, $at + 1));
    }

    /**
     * @internal
     */
    public static function fold(#[\SensitiveParameter] string $domain): string
    {
        if ('' === $domain) {
            return '';
        }

        return idn_to_ascii($domain, \IDNA_DEFAULT | \IDNA_USE_STD3_RULES | \IDNA_CHECK_BIDI | \IDNA_CHECK_CONTEXTJ | \IDNA_NONTRANSITIONAL_TO_ASCII, \INTL_IDNA_VARIANT_UTS46) ?: mb_strtolower($domain);
    }
}
