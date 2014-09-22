<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Intl\Util;

/**
 * Facilitates the comparison of ICU version strings.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class IcuVersion
{
    /**
     * Compares two ICU versions with an operator.
     *
     * This method is identical to {@link version_compare()}, except that you
     * can pass the number of regarded version components in the last argument
     * $precision.
     *
     * Also, a single digit release version and a single digit major version
     * are contracted to a two digit release version. If no major version
     * is given, it is substituted by zero.
     *
     * Examples:
     *
     *     IcuVersion::compare('1.2.3', '1.2.4', '==')
     *     // => false
     *
     *     IcuVersion::compare('1.2.3', '1.2.4', '==', 2)
     *     // => true
     *
     *     IcuVersion::compare('1.2.3', '12.3', '==')
     *     // => true
     *
     *     IcuVersion::compare('1', '10', '==')
     *     // => true
     *
     * @param string       $version1  A version string.
     * @param string       $version2  A version string to compare.
     * @param string       $operator  The comparison operator.
     * @param int|null     $precision The number of components to compare. Pass
     *                                NULL to compare the versions unchanged.
     *
     * @return bool    Whether the comparison succeeded.
     *
     * @see normalize()
     */
    public static function compare($version1, $version2, $operator, $precision = null)
    {
        $version1 = self::normalize($version1, $precision);
        $version2 = self::normalize($version2, $precision);

        return version_compare($version1, $version2, $operator);
    }

    /**
     * Normalizes a version string to the number of components given in the
     * parameter $precision.
     *
     * A single digit release version and a single digit major version are
     * contracted to a two digit release version. If no major version is given,
     * it is substituted by zero.
     *
     * Examples:
     *
     *     IcuVersion::normalize('1.2.3.4');
     *     // => '12.3.4'
     *
     *     IcuVersion::normalize('1.2.3.4', 1);
     *     // => '12'
     *
     *     IcuVersion::normalize('1.2.3.4', 2);
     *     // => '12.3'
     *
     * @param string       $version   An ICU version string.
     * @param int|null     $precision The number of components to include. Pass
     *                                NULL to return the version unchanged.
     *
     * @return string|null The normalized ICU version or NULL if it couldn't be
     *                     normalized.
     */
    public static function normalize($version, $precision)
    {
        $version = preg_replace('/^(\d)\.(\d)/', '$1$2', $version);

        if (1 === strlen($version)) {
            $version .= '0';
        }

        return Version::normalize($version, $precision);
    }

    /**
     * Must not be instantiated.
     */
    private function __construct()
    {
    }
}
