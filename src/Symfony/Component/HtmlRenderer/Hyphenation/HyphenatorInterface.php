<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HtmlRenderer\Hyphenation;

/**
 * Finds where the words of a language break with a hyphen, for CSS hyphens: auto.
 *
 * The renderer bundles no dictionaries. Supply an implementation through RenderingContext to enable automatic hyphenation.
 * Without one, or for a language it has no dictionary for, words wrap whole.
 *
 * @experimental
 */
interface HyphenatorInterface
{
    /**
     * Returns where a word may break: the number of characters (code points) before each break, in increasing order.
     *
     * It returns [] for a word without breaks, and for a language without a dictionary: it must not throw for those.
     *
     * @param string $word     Letters and marks only, without spaces, punctuation nor soft hyphens
     * @param string $language A language tag in lowercase, like "en", "en-gb" or "de-ch", from the lang attribute
     *
     * @return list<int>
     */
    public function hyphenate(string $word, string $language): array;
}
