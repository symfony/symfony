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
 *
 * @deprecated since Symfony 4.3, to be removed in 5.0.
 */
interface LanguageBundleInterface extends ResourceBundleInterface
{
    /**
     * Returns the name of a language.
     *
     * @param string      $language      A language code (e.g. "en")
     * @param string|null $region        Optional. A region code (e.g. "US")
     * @param string      $displayLocale Optional. The locale to return the name in
     *                                   Defaults to {@link \Locale::getDefault()}.
     *
     * @return string|null The name of the language or NULL if not found
     */
    public function getLanguageName($language, $region = null, $displayLocale = null);

    /**
     * Returns the names of all known languages.
     *
     * @param string $displayLocale Optional. The locale to return the names in
     *                              Defaults to {@link \Locale::getDefault()}.
     *
     * @return string[] A list of language names indexed by language codes
     */
    public function getLanguageNames($displayLocale = null);

    /**
     * Returns the name of a script.
     *
     * @param string $script        A script code (e.g. "Hans")
     * @param string $language      Optional. A language code (e.g. "zh")
     * @param string $displayLocale Optional. The locale to return the name in
     *                              Defaults to {@link \Locale::getDefault()}.
     *
     * @return string|null The name of the script or NULL if not found
     */
    public function getScriptName($script, $language = null, $displayLocale = null);

    /**
     * Returns the names of all known scripts.
     *
     * @param string $displayLocale Optional. The locale to return the names in
     *                              Defaults to {@link \Locale::getDefault()}.
     *
     * @return string[] A list of script names indexed by script codes
     */
    public function getScriptNames($displayLocale = null);
}
