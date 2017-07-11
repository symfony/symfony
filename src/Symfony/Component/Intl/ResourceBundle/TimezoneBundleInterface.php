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
 * Gives access to timezone-related ICU data.
 */
interface TimezoneBundleInterface extends ResourceBundleInterface
{
    /**
     * Returns the location name (usually a city) for a timezone.
     *
     * @param string      $zoneID        The timezone to return the name of (e.g.
     *                                   "Europe/London").
     * @param string|null $displayLocale Optional. The locale to return the name in.
     *                                   Defaults to {@link \Locale::getDefault()}.
     *
     * @return string|null The location name for the timezone or NULL if not found.
     */
    public function getTimezoneName($zoneID, $displayLocale = null);

    /**
     * Returns the location names (usually cities) for all known timezones.
     *
     * @param string|null $displayLocale Optional. The locale to return the names in.
     *                                   Defaults to {@link \Locale::getDefault()}.
     *
     * @return string[] A list of location names indexed by timezone identifiers.
     */
    public function getTimezoneNames($displayLocale = null);
}
