<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Inflector;

/**
 * Converts words between singular and plural forms.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 *
 * @internal
 */
final class Inflector
{
    /**
     * Map English plural to singular suffixes.
     *
     * @var array
     *
     * @see http://english-zone.com/spelling/plurals.html
     */
    private static $pluralMap = [
        // First entry: plural suffix, reversed
        // Second entry: length of plural suffix
        // Third entry: Whether the suffix may succeed a vocal
        // Fourth entry: Whether the suffix may succeed a consonant
        // Fifth entry: singular suffix, normal

        // bacteria (bacterium), criteria (criterion), phenomena (phenomenon)
        ['a', 1, true, true, ['on', 'um']],

        // nebulae (nebula)
        ['ea', 2, true, true, 'a'],

        // services (service)
        ['secivres', 8, true, true, 'service'],

        // mice (mouse), lice (louse)
        ['eci', 3, false, true, 'ouse'],

        // geese (goose)
        ['esee', 4, false, true, 'oose'],

        // fungi (fungus), alumni (alumnus), syllabi (syllabus), radii (radius)
        ['i', 1, true, true, 'us'],

        // men (man), women (woman)
        ['nem', 3, true, true, 'man'],

        // children (child)
        ['nerdlihc', 8, true, true, 'child'],

        // oxen (ox)
        ['nexo', 4, false, false, 'ox'],

        // indices (index), appendices (appendix), prices (price)
        ['seci', 4, false, true, ['ex', 'ix', 'ice']],

        // selfies (selfie)
        ['seifles', 7, true, true, 'selfie'],

        // movies (movie)
        ['seivom', 6, true, true, 'movie'],

        // feet (foot)
        ['teef', 4, true, true, 'foot'],

        // geese (goose)
        ['eseeg', 5, true, true, 'goose'],

        // teeth (tooth)
        ['hteet', 5, true, true, 'tooth'],

        // news (news)
        ['swen', 4, true, true, 'news'],

        // series (series)
        ['seires', 6, true, true, 'series'],

        // babies (baby)
        ['sei', 3, false, true, 'y'],

        // accesses (access), addresses (address), kisses (kiss)
        ['sess', 4, true, false, 'ss'],

        // analyses (analysis), ellipses (ellipsis), fungi (fungus),
        // neuroses (neurosis), theses (thesis), emphases (emphasis),
        // oases (oasis), crises (crisis), houses (house), bases (base),
        // atlases (atlas)
        ['ses', 3, true, true, ['s', 'se', 'sis']],

        // objectives (objective), alternative (alternatives)
        ['sevit', 5, true, true, 'tive'],

        // drives (drive)
        ['sevird', 6, false, true, 'drive'],

        // lives (life), wives (wife)
        ['sevi', 4, false, true, 'ife'],

        // moves (move)
        ['sevom', 5, true, true, 'move'],

        // hooves (hoof), dwarves (dwarf), elves (elf), leaves (leaf), caves (cave), staves (staff)
        ['sev', 3, true, true, ['f', 've', 'ff']],

        // axes (axis), axes (ax), axes (axe)
        ['sexa', 4, false, false, ['ax', 'axe', 'axis']],

        // indexes (index), matrixes (matrix)
        ['sex', 3, true, false, 'x'],

        // quizzes (quiz)
        ['sezz', 4, true, false, 'z'],

        // bureaus (bureau)
        ['suae', 4, false, true, 'eau'],

        // roses (rose), garages (garage), cassettes (cassette),
        // waltzes (waltz), heroes (hero), bushes (bush), arches (arch),
        // shoes (shoe)
        ['se', 2, true, true, ['', 'e']],

        // tags (tag)
        ['s', 1, true, true, ''],

        // chateaux (chateau)
        ['xuae', 4, false, true, 'eau'],

        // people (person)
        ['elpoep', 6, true, true, 'person'],
    ];

    /**
     * Map English singular to plural suffixes.
     *
     * @var array
     *
     * @see http://english-zone.com/spelling/plurals.html
     */
    private static $singularMap = array(
        // First entry: singular suffix, reversed
        // Second entry: length of singular suffix
        // Third entry: Whether the suffix may succeed a vocal
        // Fourth entry: Whether the suffix may succeed a consonant
        // Fifth entry: plural suffix, normal

        // criterion (criteria)
        array('airetirc', 8, false, false, 'criterion'),

        // nebulae (nebula)
        array('aluben', 6, false, false, 'nebulae'),

        // children (child)
        array('dlihc', 5, true, true, 'children'),

        // prices (price)
        array('eci', 3, false, true, 'ices'),

        // services (service)
        array('ecivres', 7, true, true, 'services'),

        // lives (life), wives (wife)
        array('efi', 3, false, true, 'ives'),

        // selfies (selfie)
        array('eifles', 6, true, true, 'selfies'),

        // movies (movie)
        array('eivom', 5, true, true, 'movies'),

        // lice (louse)
        array('esuol', 5, false, true, 'lice'),

        // mice (mouse)
        array('esuom', 5, false, true, 'mice'),

        // geese (goose)
        array('esoo', 4, false, true, 'eese'),

        // houses (house), bases (base)
        array('es', 2, true, true, 'ses'),

        // geese (goose)
        array('esoog', 5, true, true, 'geese'),

        // caves (cave)
        array('ev', 2, true, true, 'ves'),

        // drives (drive)
        array('evird', 5, false, true, 'drives'),

        // objectives (objective), alternative (alternatives)
        array('evit', 4, true, true, 'tives'),

        // moves (move)
        array('evom', 4, true, true, 'moves'),

        // staves (staff)
        array('ffats', 5, true, true, 'staves'),

        // hooves (hoof), dwarves (dwarf), elves (elf), leaves (leaf)
        array('ff', 2, true, true, 'ffs'),

        // hooves (hoof), dwarves (dwarf), elves (elf), leaves (leaf)
        array('f', 1, true, true, array('fs', 'ves')),

        // arches (arch)
        array('hc', 2, true, true, 'ches'),

        // bushes (bush)
        array('hs', 2, true, true, 'shes'),

        // teeth (tooth)
        array('htoot', 5, true, true, 'teeth'),

        // bacteria (bacterium), criteria (criterion), phenomena (phenomenon)
        array('mu', 2, true, true, 'a'),

        // echoes (echo)
        array('ohce', 4, true, true, 'echoes'),

        // men (man), women (woman)
        array('nam', 3, true, true, 'men'),

        // people (person)
        array('nosrep', 6, true, true, array('persons', 'people')),

        // bacteria (bacterium), criteria (criterion), phenomena (phenomenon)
        array('noi', 3, true, true, 'ions'),

        // bacteria (bacterium), criteria (criterion), phenomena (phenomenon)
        array('no', 2, true, true, 'a'),

        // atlases (atlas)
        array('salta', 5, true, true, 'atlases'),

        // irises (iris)
        array('siri', 4, true, true, 'irises'),

        // analyses (analysis), ellipses (ellipsis), neuroses (neurosis)
        // theses (thesis), emphases (emphasis), oases (oasis),
        // crises (crisis)
        array('sis', 3, true, true, 'ses'),

        // accesses (access), addresses (address), kisses (kiss)
        array('ss', 2, true, false, 'sses'),

        // syllabi (syllabus)
        array('suballys', 8, true, true, 'syllabi'),

        // buses (bus)
        array('sub', 3, true, true, 'buses'),

        // fungi (fungus), alumni (alumnus), syllabi (syllabus), radii (radius)
        array('su', 2, true, true, 'i'),

        // news (news)
        array('swen', 4, true, true, 'news'),

        // feet (foot)
        array('toof', 4, true, true, 'feet'),

        // chateaux (chateau), bureaus (bureau)
        array('uae', 3, false, true, array('eaus', 'eaux')),

        // oxen (ox)
        array('xo', 2, false, false, 'oxen'),

        // hoaxes (hoax)
        array('xaoh', 4, true, false, 'hoaxes'),

        // indices (index)
        array('xedni', 5, false, true, array('indicies', 'indexes')),

        // indexes (index), matrixes (matrix)
        array('x', 1, true, false, array('cies', 'xes')),

        // appendices (appendix)
        array('xi', 2, false, true, 'ices'),

        // babies (baby)
        array('y', 1, false, true, 'ies'),

        // quizzes (quiz)
        array('ziuq', 4, true, false, 'quizzes'),

        // waltzes (waltz)
        array('z', 1, true, false, 'zes'),
    );

    /**
     * A list of words which should not be inflected
     *
     * @var array
     */
    private static $uninflected = array(
        'data',
        'deer',
        'feedback',
        'fish',
        'moose',
        'series',
        'sheep',
    );

    /**
     * This class should not be instantiated.
     */
    private function __construct()
    {
    }

    /**
     * Returns the singular form of a word.
     *
     * If the method can't determine the form with certainty, an array of the
     * possible singulars is returned.
     *
     * @param string $plural A word in plural form
     *
     * @return string|array The singular form or an array of possible singular
     *                      forms
     *
     * @internal
     */
    public static function singularize(string $plural)
    {
        $pluralRev = strrev($plural);
        $lowerPluralRev = strtolower($pluralRev);
        $pluralLength = \strlen($lowerPluralRev);

        // Check if the word is one which is not inflected, return early if so
        if (in_array(strtolower($plural), self::$uninflected, true)) {
            return $plural;
        }

        // The outer loop iterates over the entries of the plural table
        // The inner loop $j iterates over the characters of the plural suffix
        // in the plural table to compare them with the characters of the actual
        // given plural suffix
        foreach (self::$pluralMap as $map) {
            $suffix = $map[0];
            $suffixLength = $map[1];
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

                        if (!$map[2] && $nextIsVocal) {
                            // suffix may not succeed a vocal but next char is one
                            break;
                        }

                        if (!$map[3] && !$nextIsVocal) {
                            // suffix may not succeed a consonant but next char is one
                            break;
                        }
                    }

                    $newBase = substr($plural, 0, $pluralLength - $suffixLength);
                    $newSuffix = $map[4];

                    // Check whether the first character in the plural suffix
                    // is uppercased. If yes, uppercase the first character in
                    // the singular suffix too
                    $firstUpper = ctype_upper($pluralRev[$j - 1]);

                    if (\is_array($newSuffix)) {
                        $singulars = [];

                        foreach ($newSuffix as $newSuffixEntry) {
                            $singulars[] = $newBase.($firstUpper ? ucfirst($newSuffixEntry) : $newSuffixEntry);
                        }

                        return $singulars;
                    }

                    return $newBase.($firstUpper ? ucfirst($newSuffix) : $newSuffix);
                }

                // Suffix is longer than word
                if ($j === $pluralLength) {
                    break;
                }
            }
        }

        // Assume that plural and singular is identical
        return $plural;
    }

    /**
     * Returns the plural form of a word.
     *
     * If the method can't determine the form with certainty, an array of the
     * possible plurals is returned.
     *
     * @param string $singular A word in plural form
     *
     * @return string|array The plural form or an array of possible plural
     *                      forms
     *
     * @internal
     */
    public static function pluralize(string $singular)
    {
        $singularRev = strrev($singular);
        $lowerSingularRev = strtolower($singularRev);
        $singularLength = strlen($lowerSingularRev);

        // Check if the word is one which is not inflected, return early if so
        if (in_array(strtolower($singular), self::$uninflected, true)) {
            return $singular;
        }

        // The outer loop iterates over the entries of the singular table
        // The inner loop $j iterates over the characters of the singular suffix
        // in the singular table to compare them with the characters of the actual
        // given singular suffix
        foreach (self::$singularMap as $map) {
            $suffix = $map[0];
            $suffixLength = $map[1];
            $j = 0;

            // Compare characters in the singular table and of the suffix of the
            // given plural one by one

            while ($suffix[$j] === $lowerSingularRev[$j]) {
                // Let $j point to the next character
                ++$j;

                // Successfully compared the last character
                // Add an entry with the plural suffix to the plural array
                if ($j === $suffixLength) {
                    // Is there any character preceding the suffix in the plural string?
                    if ($j < $singularLength) {
                        $nextIsVocal = false !== strpos('aeiou', $lowerSingularRev[$j]);

                        if (!$map[2] && $nextIsVocal) {
                            // suffix may not succeed a vocal but next char is one
                            break;
                        }

                        if (!$map[3] && !$nextIsVocal) {
                            // suffix may not succeed a consonant but next char is one
                            break;
                        }
                    }

                    $newBase = substr($singular, 0, $singularLength - $suffixLength);
                    $newSuffix = $map[4];

                    // Check whether the first character in the singular suffix
                    // is uppercased. If yes, uppercase the first character in
                    // the singular suffix too
                    $firstUpper = ctype_upper($singularRev[$j - 1]);

                    if (is_array($newSuffix)) {
                        $plurals = array();

                        foreach ($newSuffix as $newSuffixEntry) {
                            $plurals[] = $newBase.($firstUpper ? ucfirst($newSuffixEntry) : $newSuffixEntry);
                        }

                        return $plurals;
                    }

                    return $newBase.($firstUpper ? ucfirst($newSuffix) : $newSuffix);
                }

                // Suffix is longer than word
                if ($j === $singularLength) {
                    break;
                }
            }
        }

        // Assume that plural is singular with a trailing `s`
        return $singular.'s';
    }
}
