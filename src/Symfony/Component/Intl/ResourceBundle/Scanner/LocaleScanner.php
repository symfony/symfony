<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Intl\ResourceBundle\Scanner;

/**
 * Scans a directory with text data files for locales.
 *
 * !!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!
 * Do NOT use this class in your own code. Backwards compatibility can NOT be
 * guaranteed and BC breaks will NOT be documented.
 * !!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!
 *
 * The name of each *.txt file (without suffix) in the given source directory
 * is considered a locale.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class LocaleScanner
{
    /**
     * A list of known non-locales.
     *
     * @var array
     */
    private static $blackList = array(
        'root',
        'misc',
        'supplementalData',
        'supplementaldata',
    );

    /**
     * Returns all locales found in the given directory.
     *
     * @param string $sourceDir The directory with ICU *.txt files.
     *
     * @return array An array of locales. The result also contains locales that
     *               are in fact just aliases for other locales. Use
     *               {@link scanAliases()} to determine which of the locales
     *               are aliases.
     */
    public function scanLocales($sourceDir)
    {
        $locales = glob($sourceDir.'/*.txt');

        // Remove file extension and sort
        array_walk($locales, function (&$locale) { $locale = basename($locale, '.txt'); });

        // Remove non-locales
        $locales = array_diff($locales, static::$blackList);

        sort($locales);

        return $locales;
    }

    /**
     * Returns all locale aliases found in the given directory.
     *
     * @param string $sourceDir The directory with ICU *.txt files.
     *
     * @return array An array with the locale aliases as keys and the aliased
     *               locales as values.
     */
    public function scanAliases($sourceDir)
    {
        $locales = $this->scanLocales($sourceDir);
        $aliases = array();

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
