<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Util;

/**
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
abstract class FormUtil
{
    const PLURAL_SUFFIX = 0;

    const PLURAL_SUFFIX_LENGTH = 1;

    const PLURAL_SUFFIX_AFTER_VOCAL = 2;

    const PLURAL_SUFFIX_AFTER_CONS = 3;

    const SINGULAR_SUFFIX = 4;

    /**
     * Map english plural to singular suffixes
     *
     * @var array
     *
     * @see http://english-zone.com/spelling/plurals.html
     * @see http://www.scribd.com/doc/3271143/List-of-100-Irregular-Plural-Nouns-in-English
     */
    private static $pluralMap = array(
        // First entry: plural suffix, reversed
        // Second entry: length of plural suffix
        // Third entry: Whether the suffix may succeed a vocal
        // Fourth entry: Whether the suffix may succeed a consonant
        // Fifth entry: singular suffix, normal

        // bacteria (bacterium), criteria (criterion), phenomena (phenomenon)
        array('a', 1, true, true, array('on', 'um')),

        // nebulae (nebula)
        array('ea', 2, true, true, 'a'),

        // mice (mouse), lice (louse)
        array('eci', 3, false, true, 'ouse'),

        // geese (goose)
        array('esee', 4, false, true, 'oose'),

        // fungi (fungus), alumni (alumnus), syllabi (syllabus), radii (radius)
        array('i', 1, true, true, 'us'),

        // men (man), women (woman)
        array('nem', 3, true, true, 'man'),

        // children (child)
        array('nerdlihc', 8, true, true, 'child'),

        // oxen (ox)
        array('nexo', 4, false, false, 'ox'),

        // indices (index), appendices (appendix)
        array('seci', 4, false, true, array('ex', 'ix')),

        // babies (baby)
        array('sei', 3, false, true, 'y'),

        // analyses (analysis), ellipses (ellipsis), funguses (fungus),
        // neuroses (neurosis), theses (thesis), emphases (emphasis),
        // oases (oasis), crises (crisis), houses (house), bases (base),
        // atlases (atlas), kisses (kiss)
        array('ses', 3, true, true, array('s', 'se', 'sis')),

        // lives (life), wives (wife)
        array('sevi', 4, false, true, 'ife'),

        // hooves (hoof), dwarves (dwarf), elves (elf), leaves (leaf)
        array('sev', 3, true, true, 'f'),

        // axes (axis), axes (ax), axes (axe)
        array('sexa', 4, false, false, array('ax', 'axe', 'axis')),

        // indexes (index), matrixes (matrix)
        array('sex', 3, true, false, 'x'),

        // quizzes (quiz)
        array('sezz', 4, true, false, 'z'),

        // bureaus (bureau)
        array('suae', 4, false, true, 'eau'),

        // roses (rose), garages (garage), cassettes (cassette),
        // waltzes (waltz), heroes (hero), bushes (bush), arches (arch),
        // shoes (shoe)
        array('se', 2, true, true, array('', 'e')),

        // tags (tag)
        array('s', 1, true, true, ''),

        // chateaux (chateau)
        array('xuae', 4, false, true, 'eau'),
    );

    /**
     * Returns the singular form of a word
     *
     * If the method can't determine the form with certainty, an array of the
     * possible singulars is returned.
     *
     * @param string $plural A word in plural form
     * @return string|array The singular form or an array of possible singular
     *                      forms
     */
    public static function singularify($plural)
    {
        $pluralRev = strrev($plural);
        $lowerPluralRev = strtolower($pluralRev);
        $pluralLength = strlen($lowerPluralRev);

        // The outer loop $i iterates over the entries of the plural table
        // The inner loop $j iterates over the characters of the plural suffix
        // in the plural table to compare them with the characters of the actual
        // given plural suffix
        for ($i = 0, $numPlurals = count(self::$pluralMap); $i < $numPlurals; ++$i) {
            $suffix = self::$pluralMap[$i][self::PLURAL_SUFFIX];
            $suffixLength = self::$pluralMap[$i][self::PLURAL_SUFFIX_LENGTH];
            $j = 0;

            // Compare characters in the plural table and of the suffix of the
            // given plural one by one
            while ($suffix[$j] === $lowerPluralRev[$j]) {
                // Let $j point to the next character
                ++$j;

                // Successfully compared the last character
                // Add an entry with the singular suffix to the singular array
                if ($j === $suffixLength) {
                    // Is there any character preceding the suffix in the plural string?
                    if ($j < $pluralLength) {
                        $nextIsVocal = false !== strpos('aeiou', $lowerPluralRev[$j]);

                        if (!self::$pluralMap[$i][self::PLURAL_SUFFIX_AFTER_VOCAL] && $nextIsVocal) {
                            break;
                        }

                        if (!self::$pluralMap[$i][self::PLURAL_SUFFIX_AFTER_CONS] && !$nextIsVocal) {
                            break;
                        }
                    }

                    $newBase = substr($plural, 0, $pluralLength - $suffixLength);
                    $newSuffix = self::$pluralMap[$i][self::SINGULAR_SUFFIX];

                    // Check whether the first character in the plural suffix
                    // is uppercased. If yes, uppercase the first character in
                    // the singular suffix too
                    $firstUpper = ctype_upper($pluralRev[$j - 1]);

                    if (is_array($newSuffix)) {
                        $singulars = array();

                        foreach ($newSuffix as $newSuffixEntry) {
                            $singulars[] = $newBase . ($firstUpper ? ucfirst($newSuffixEntry) : $newSuffixEntry);
                        }

                        return $singulars;
                    }

                    return $newBase . ($firstUpper ? ucFirst($newSuffix) : $newSuffix);
                }

                // Suffix is longer than word
                if ($j === $pluralLength) {
                    break;
                }
            }
        }

        // Convert teeth to tooth, feet to foot
        if (false !== ($pos = strpos($plural, 'ee'))) {
            return substr_replace($plural, 'oo', $pos, 2);
        }

        // Assume that plural and singular is identical
        return $plural;
    }

    /**
     * Returns whether the given data is empty.
     *
     * This logic is reused multiple times throughout the processing of
     * a form and needs to be consistent. PHP's keyword `empty` cannot
     * be used as it also considers 0 and "0" to be empty.
     *
     * @param  mixed $data
     *
     * @return Boolean
     */
    public static function isEmpty($data)
    {
        // Should not do a check for array() === $data!!!
        // This method is used in occurrences where arrays are
        // not considered to be empty, ever.
        return null === $data || '' === $data;
    }
}
