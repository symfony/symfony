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
     * @var string|null
     */
    private static $defaultFallback = 'en';

    /**
     * Sets the default fallback locale.
     *
     * The default fallback locale is used as fallback for locales that have no
     * fallback otherwise.
     *
     * @param string|null $locale The default fallback locale
     *
     * @see getFallback()
     */
    public static function setDefaultFallback(?string $locale)
    {
        self::$defaultFallback = $locale;
    }

    /**
     * Returns the default fallback locale.
     *
     * @return string|null The default fallback locale
     *
     * @see setDefaultFallback()
     * @see getFallback()
     */
    public static function getDefaultFallback(): ?string
    {
        return self::$defaultFallback;
    }

    /**
     * Returns the fallback locale for a given locale.
     *
     * For example, the fallback of "fr_FR" is "fr". The fallback of "fr" is
     * the default fallback locale configured with {@link setDefaultFallback()}.
     * The default fallback locale has no fallback.
     *
     * @param string $locale The ICU locale code to find the fallback for
     *
     * @return string|null The ICU locale code of the fallback locale, or null
     *                     if no fallback exists
     */
    public static function getFallback(string $locale): ?string
    {
        if (\function_exists('locale_parse')) {
            $localeSubTags = locale_parse($locale);
            if (1 === \count($localeSubTags)) {
                if ('root' !== self::$defaultFallback && self::$defaultFallback === $localeSubTags['language']) {
                    return 'root';
                }

                // Don't return default fallback for "root", "meta" or others
                // Normal locales have two or three letters
                if (\strlen($locale) < 4) {
                    return self::$defaultFallback;
                }

                return null;
            }

            array_pop($localeSubTags);

            $fallback = locale_compose($localeSubTags);

            return false !== $fallback ? $fallback : null;
        }

        if (false !== $pos = strrpos($locale, '_')) {
            return substr($locale, 0, $pos);
        }

        if (false !== $pos = strrpos($locale, '-')) {
            return substr($locale, 0, $pos);
        }

        if ('root' !== self::$defaultFallback && self::$defaultFallback === $locale) {
            return 'root';
        }

        // Don't return default fallback for "root", "meta" or others
        // Normal locales have two or three letters
        return \strlen($locale) < 4 ? self::$defaultFallback : null;
    }

    /**
     * This class must not be instantiated.
     */
    private function __construct()
    {
    }
}
