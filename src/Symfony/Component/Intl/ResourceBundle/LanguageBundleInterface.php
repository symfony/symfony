<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Intl\ResourceBundle;

/**
 * Gives access to language-related ICU data.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
interface LanguageBundleInterface extends ResourceBundleInterface
{
    /**
     * Returns the name of a language.
     *
     * @param string      $locale The locale to return the name in.
     * @param string      $lang   A language code (e.g. "en").
     * @param string|null $region Optional. A region code (e.g. "US").
     *
     * @return string|null The name of the language or NULL if not found.
     */
    public function getLanguageName($locale, $lang, $region = null);

    /**
     * Returns the names of all known languages.
     *
     * @param string $locale The locale to return the names in.
     *
     * @return string[] A list of language names indexed by language codes.
     */
    public function getLanguageNames($locale);

    /**
     * Returns the name of a script.
     *
     * @param string $locale The locale to return the name in.
     * @param string $script A script code (e.g. "Hans").
     * @param string $lang   Optional. A language code (e.g. "zh").
     *
     * @return string|null The name of the script or NULL if not found.
     */
    public function getScriptName($locale, $script, $lang = null);

    /**
     * Returns the names of all known scripts.
     *
     * @param string $locale The locale to return the names in.
     *
     * @return string[] A list of script names indexed by script codes.
     */
    public function getScriptNames($locale);
}
