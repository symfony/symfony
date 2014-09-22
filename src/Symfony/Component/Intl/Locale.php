<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Intl;

/**
 * Provides access to locale-related data.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 *
 * @internal
 */
final class Locale extends \Locale
{
    /**
     * Returns the fallback locale for a given locale, if any
     *
     * @param string $locale The ICU locale code to find the fallback for.
     *
     * @return string|null The ICU locale code of the fallback locale, or null
     *                     if no fallback exists
     */
    public static function getFallback($locale)
    {
        if (false === $pos = strrpos($locale, '_')) {
            if ('root' === $locale) {
                return;
            }

            return 'root';
        }

        return substr($locale, 0, $pos);
    }

    /**
     * This class must not be instantiated.
     */
    private function __construct()
    {
    }
}
