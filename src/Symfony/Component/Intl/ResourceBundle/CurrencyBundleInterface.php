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
 * Gives access to currency-related ICU data.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
interface CurrencyBundleInterface extends ResourceBundleInterface
{
    /**
     * Returns the symbol used for a currency.
     *
     * @param string $locale   The locale to return the result in.
     * @param string $currency A currency code (e.g. "EUR").
     *
     * @return string|null The currency symbol or NULL if not found.
     */
    public function getCurrencySymbol($locale, $currency);

    /**
     * Returns the name of a currency.
     *
     * @param string $locale   The locale to return the name in.
     * @param string $currency A currency code (e.g. "EUR").
     *
     * @return string|null The name of the currency or NULL if not found.
     */
    public function getCurrencyName($locale, $currency);

    /**
     * Returns the names of all known currencies.
     *
     * @param string $locale The locale to return the names in.
     *
     * @return string[] A list of currency names indexed by currency codes.
     */
    public function getCurrencyNames($locale);

    /**
     * Returns the number of digits after the comma of a currency.
     *
     * @param string $currency A currency code (e.g. "EUR").
     *
     * @return integer|null The number of digits after the comma or NULL if not found.
     */
    public function getFractionDigits($currency);

    /**
     * Returns the rounding increment of a currency.
     *
     * The rounding increment indicates to which number a currency is rounded.
     * For example, 1230 rounded to the nearest 50 is 1250. 1.234 rounded to the
     * nearest 0.65 is 1.3.
     *
     * @param string $currency A currency code (e.g. "EUR").
     *
     * @return float|integer|null The rounding increment or NULL if not found.
     */
    public function getRoundingIncrement($currency);
}
