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
 * Gives access to region-related ICU data.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
interface RegionBundleInterface extends ResourceBundleInterface
{
    /**
     * Returns the name of a country.
     *
     * @param string $country       A country code (e.g. "US").
     * @param string $displayLocale Optional. The locale to return the name in.
     *                              Defaults to {@link \Locale::getDefault()}.
     *
     * @return string|null The name of the country or NULL if not found.
     */
    public function getCountryName($country, $displayLocale = null);

    /**
     * Returns the names of all known countries.
     *
     * @param string $displayLocale Optional. The locale to return the names in.
     *                              Defaults to {@link \Locale::getDefault()}.
     *
     * @return string[] A list of country names indexed by country codes.
     */
    public function getCountryNames($displayLocale = null);
}
