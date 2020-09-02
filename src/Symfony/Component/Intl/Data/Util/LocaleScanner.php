<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Intl\Data\Util;

/**
 * Scans a directory with data files for locales.
 *
 * The name of each file with the extension ".txt" is considered, if it "looks"
 * like a locale:
 *
 *  - the name must start with two letters;
 *  - the two letters may optionally be followed by an underscore and any
 *    sequence of other symbols.
 *
 * For example, "de" and "de_DE" are considered to be locales. "root" and "meta"
 * are not.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 *
 * @internal
 */
class LocaleScanner
{
    /**
     * Returns all locales found in the given directory.
     *
     * @param string $sourceDir The directory with ICU files
     *
     * @return array An array of locales. The result also contains locales that
     *               are in fact just aliases for other locales. Use
     *               {@link scanAliases()} to determine which of the locales
     *               are aliases
     */
    public function scanLocales($sourceDir)
    {
        $locales = glob($sourceDir.'/*.txt', \GLOB_NOSORT);

        // Remove file extension and sort
        array_walk($locales, function (&$locale) { $locale = basename($locale, '.txt'); });

        // Remove non-locales
        $locales = array_filter($locales, function ($locale) {
            return preg_match('/^[a-z]{2}(_.+)?$/', $locale);
        });

        sort($locales);

        return $locales;
    }

    /**
     * Returns all locale aliases found in the given directory.
     *
     * @param string $sourceDir The directory with ICU files
     *
     * @return array An array with the locale aliases as keys and the aliased
     *               locales as values
     */
    public function scanAliases($sourceDir)
    {
        $locales = $this->scanLocales($sourceDir);
        $aliases = [];

        // Delete locales that are no aliases
        foreach ($locales as $locale) {
            $content = file_get_contents($sourceDir.'/'.$locale.'.txt');

            // Aliases contain the text "%%ALIAS" followed by the aliased locale
            if (preg_match('/"%%ALIAS"\{"([^"]+)"\}/', $content, $matches)) {
                $aliases[$locale] = $matches[1];
            }
        }

        return $aliases;
    }
}
