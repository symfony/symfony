<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Inflector;

use Symfony\Component\String\Inflector\EnglishInflector;

trigger_deprecation('symfony/inflector', '5.1', 'The "%s" class is deprecated, use "%s" instead.', Inflector::class, EnglishInflector::class);

/**
 * Converts words between singular and plural forms.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 *
 * @deprecated since Symfony 5.1, use Symfony\Component\String\Inflector\EnglishInflector instead.
 */
final class Inflector
{
    private static $englishInflector;

    /**
     * This class should not be instantiated.
     */
    private function __construct()
    {
    }

    /**
     * Returns the singular form of a word.
     *
     * If the method can't determine the form with certainty, an array of the
     * possible singulars is returned.
     *
     * @param string $plural A word in plural form
     *
     * @return string|array
     */
    public static function singularize(string $plural)
    {
        if (1 === \count($singulars = self::getEnglishInflector()->singularize($plural))) {
            return $singulars[0];
        }

        return $singulars;
    }

    /**
     * Returns the plural form of a word.
     *
     * If the method can't determine the form with certainty, an array of the
     * possible plurals is returned.
     *
     * @param string $singular A word in singular form
     *
     * @return string|array
     */
    public static function pluralize(string $singular)
    {
        if (1 === \count($plurals = self::getEnglishInflector()->pluralize($singular))) {
            return $plurals[0];
        }

        return $plurals;
    }

    private static function getEnglishInflector(): EnglishInflector
    {
        if (!self::$englishInflector) {
            self::$englishInflector = new EnglishInflector();
        }

        return self::$englishInflector;
    }
}
