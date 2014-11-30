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
 * Facilitates the comparison of version strings.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class Version
{
    /**
     * Compares two versions with an operator.
     *
     * This method is identical to {@link version_compare()}, except that you
     * can pass the number of regarded version components in the last argument
     * $precision.
     *
     * Examples:
     *
     *     Version::compare('1.2.3', '1.2.4', '==')
     *     // => false
     *
     *     Version::compare('1.2.3', '1.2.4', '==', 2)
     *     // => true
     *
     * @param string   $version1  A version string.
     * @param string   $version2  A version string to compare.
     * @param string   $operator  The comparison operator.
     * @param int|null $precision The number of components to compare. Pass
     *                            NULL to compare the versions unchanged.
     *
     * @return bool Whether the comparison succeeded.
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
     * Examples:
     *
     *     Version::normalize('1.2.3', 1);
     *     // => '1'
     *
     *     Version::normalize('1.2.3', 2);
     *     // => '1.2'
     *
     * @param string   $version   A version string.
     * @param int|null $precision The number of components to include. Pass
     *                            NULL to return the version unchanged.
     *
     * @return string|null The normalized version or NULL if it couldn't be
     *                     normalized.
     */
    public static function normalize($version, $precision)
    {
        if (null === $precision) {
            return $version;
        }

        $pattern = '[^\.]+';

        for ($i = 2; $i <= $precision; ++$i) {
            $pattern = sprintf('[^\.]+(\.%s)?', $pattern);
        }

        if (!preg_match('/^'.$pattern.'/', $version, $matches)) {
            return;
        }

        return $matches[0];
    }

    /**
     * Must not be instantiated.
     */
    private function __construct()
    {
    }
}
