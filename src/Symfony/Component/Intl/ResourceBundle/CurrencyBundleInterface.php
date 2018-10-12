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
 *
 * @deprecated since Symfony 4.3, to be removed in 5.0.
 */
interface CurrencyBundleInterface extends ResourceBundleInterface
{
    /**
     * Returns the symbol used for a currency.
     *
     * @param string $currency      A currency code (e.g. "EUR")
     * @param string $displayLocale Optional. The locale to return the result in
     *                              Defaults to {@link \Locale::getDefault()}.
     *
     * @return string|null The currency symbol or NULL if not found
     */
    public function getCurrencySymbol($currency, $displayLocale = null);

    /**
     * Returns the name of a currency.
     *
     * @param string $currency      A currency code (e.g. "EUR")
     * @param string $displayLocale Optional. The locale to return the name in
     *                              Defaults to {@link \Locale::getDefault()}.
     *
     * @return string|null The name of the currency or NULL if not found
     */
    public function getCurrencyName($currency, $displayLocale = null);

    /**
     * Returns the names of all known currencies.
     *
     * @param string $displayLocale Optional. The locale to return the names in
     *                              Defaults to {@link \Locale::getDefault()}.
     *
     * @return string[] A list of currency names indexed by currency codes
     */
    public function getCurrencyNames($displayLocale = null);

    /**
     * Returns the number of digits after the comma of a currency.
     *
     * @param string $currency A currency code (e.g. "EUR")
     *
     * @return int|null The number of digits after the comma or NULL if not found
     */
    public function getFractionDigits($currency);

    /**
     * Returns the rounding increment of a currency.
     *
     * The rounding increment indicates to which number a currency is rounded.
     * For example, 1230 rounded to the nearest 50 is 1250. 1.234 rounded to the
     * nearest 0.65 is 1.3.
     *
     * @param string $currency A currency code (e.g. "EUR")
     *
     * @return float|int|null The rounding increment or NULL if not found
     */
    public function getRoundingIncrement($currency);
}
