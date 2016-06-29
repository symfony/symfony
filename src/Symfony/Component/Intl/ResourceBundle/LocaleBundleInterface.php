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
 * Gives access to locale-related ICU data.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
interface LocaleBundleInterface extends ResourceBundleInterface
{
    /**
     * Returns the name of a locale.
     *
     * @param string $locale        The locale to return the name of (e.g. "de_AT")
     * @param string $displayLocale Optional. The locale to return the name in
     *                              Defaults to {@link \Locale::getDefault()}.
     *
     * @return string|null The name of the locale or NULL if not found
     */
    public function getLocaleName($locale, $displayLocale = null);

    /**
     * Returns the names of all known locales.
     *
     * @param string $displayLocale Optional. The locale to return the names in
     *                              Defaults to {@link \Locale::getDefault()}.
     *
     * @return string[] A list of locale names indexed by locale codes
     */
    public function getLocaleNames($displayLocale = null);
}
