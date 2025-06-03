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
     * @param int|null $precision The number of components to compare. Pass
     *                            NULL to compare the versions unchanged.
     *
     * @see normalize()
     */
    public static function compare(string $version1, string $version2, string $operator, ?int $precision = null): bool
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
     * @param int|null $precision The number of components to include. Pass
     *                            NULL to return the version unchanged.
     */
    public static function normalize(string $version, ?int $precision): ?string
    {
        if (null === $precision) {
            return $version;
        }

        $pattern = '[^\.]+';

        for ($i = 2; $i <= $precision; ++$i) {
            $pattern = \sprintf('[^\.]+(\.%s)?', $pattern);
        }

        if (!preg_match('/^'.$pattern.'/', $version, $matches)) {
            return null;
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
