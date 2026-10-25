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

use Symfony\Component\Intl\Exception\MissingResourceException;

/**
 * Gives access to locale-related ICU data.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 * @author Roland Franssen <franssen.roland@gmail.com>
 */
final class Locales extends ResourceBundle
{
    /**
     * @var array<string, array{string, ?string, ?string}>
     */
    private static array $subtags;

    /**
     * @var array<string, array{string, string}>
     */
    private static array $patterns = [];

    /**
     * @return string[]
     */
    public static function getLocales(): array
    {
        return self::readEntry(['Locales'], 'meta');
    }

    /**
     * @return string[]
     */
    public static function getAliases(): array
    {
        return self::readEntry(['Aliases'], 'meta');
    }

    public static function exists(string $locale): bool
    {
        try {
            self::readLocaleName($locale, \Locale::getDefault());

            return true;
        } catch (MissingResourceException) {
            return \in_array($locale, self::getAliases(), true);
        }
    }

    /**
     * @throws MissingResourceException if the locale does not exist
     */
    public static function getName(string $locale, ?string $displayLocale = null): string
    {
        $displayLocale ??= \Locale::getDefault();

        try {
            return self::readLocaleName($locale, $displayLocale);
        } catch (MissingResourceException $e) {
            if (false === $aliased = array_search($locale, self::getAliases(), true)) {
                throw $e;
            }

            return self::readLocaleName($aliased, $displayLocale);
        }
    }

    /**
     * @return string[]
     */
    public static function getNames(?string $displayLocale = null): array
    {
        return self::asort(self::readLocaleNames($displayLocale ?? \Locale::getDefault()), $displayLocale);
    }

    protected static function getPath(): string
    {
        return Intl::getDataDirectory().'/'.Intl::LOCALE_DIR;
    }

    /**
     * Builds the name of a locale from the names of its language, script and region.
     *
     * The display locale or its parents must translate all these names, otherwise the name is built in the default fallback locale (see Locale::getDefaultFallback()).
     */
    private static function readLocaleName(string $locale, string $displayLocale): string
    {
        if (isset(self::getSubtags()[$locale])) {
            [$language, $script, $region] = self::getSubtags()[$locale];

            foreach (self::getDisplayLocales($displayLocale) as $inLocale) {
                try {
                    $languageName = self::escapeName(Languages::readName($language, $inLocale, false));
                    $scriptName = null !== $script ? self::escapeName(Scripts::readName($script, $inLocale, false)) : null;
                    $regionName = null !== $region ? self::escapeName(self::readRegionName($region, $inLocale)) : null;
                } catch (MissingResourceException) {
                    continue;
                }

                return self::formatName($languageName, $scriptName, $regionName, self::readPatterns($inLocale));
            }
        }

        throw new MissingResourceException(\sprintf('The locale "%s" has no name in the locale "%s".', $locale, $displayLocale));
    }

    /**
     * @return array<string, string>
     */
    private static function readLocaleNames(string $displayLocale): array
    {
        $sources = [];

        foreach (self::getDisplayLocales($displayLocale) as $inLocale) {
            if (!$languages = self::readNamesOf(Languages::class, $inLocale)) {
                continue;
            }

            $regions = self::readNamesOf(Countries::class, $inLocale);

            try {
                foreach (Countries::readEntry(['NumericNames'], $inLocale, true, false) as $region => $name) {
                    $regions[$region] = self::escapeName($name);
                }
            } catch (MissingResourceException) {
            }

            $sources[] = [$languages, self::readNamesOf(Scripts::class, $inLocale), $regions, self::readPatterns($inLocale)];
        }

        if (!$sources) {
            throw new MissingResourceException(\sprintf('The locale "%s" has no locale names.', $displayLocale));
        }

        $localeNames = [];

        foreach (self::getSubtags() as $locale => [$language, $script, $region]) {
            foreach ($sources as [$languages, $scripts, $regions, $patterns]) {
                if (isset($languages[$language]) && (null === $script || isset($scripts[$script])) && (null === $region || isset($regions[$region]))) {
                    $localeNames[$locale] = self::formatName($languages[$language], null !== $script ? $scripts[$script] : null, null !== $region ? $regions[$region] : null, $patterns);
                    break;
                }
            }
        }

        return $localeNames;
    }

    /**
     * Returns the display locale, followed by the default locale when the bundle reader falls back to it.
     *
     * @return string[]
     */
    private static function getDisplayLocales(string $displayLocale): array
    {
        $defaultFallback = Locale::getDefaultFallback();

        if (null !== $defaultFallback && $defaultFallback === Locale::getFallback(strstr($displayLocale.'_', '_', true))) {
            return [$displayLocale, $defaultFallback];
        }

        return [$displayLocale];
    }

    /**
     * @param class-string<ResourceBundle> $bundle
     *
     * @return array<string, string>
     */
    private static function readNamesOf(string $bundle, string $displayLocale): array
    {
        try {
            $names = $bundle::readNames($displayLocale, false);
        } catch (MissingResourceException) {
            return [];
        }

        foreach ($names as $code => $name) {
            $names[$code] = self::escapeName($name);
        }

        return $names;
    }

    private static function readRegionName(string $region, string $displayLocale): string
    {
        if (3 === \strlen($region)) {
            return Countries::readEntry(['NumericNames', $region], $displayLocale, true, false);
        }

        return Countries::readName($region, $displayLocale, false);
    }

    /**
     * @return array{string, string}
     */
    private static function readPatterns(string $displayLocale): array
    {
        return self::$patterns[$displayLocale] ??= [self::readPattern('Pattern', $displayLocale), self::readPattern('Separator', $displayLocale)];
    }

    private static function readPattern(string $name, string $displayLocale): string
    {
        try {
            return Languages::readEntry([$name], $displayLocale, true, false);
        } catch (MissingResourceException) {
            return Languages::readEntry([$name], 'root');
        }
    }

    /**
     * Replaces parentheses with square brackets, as parentheses enclose the script and the region in locale names.
     */
    private static function escapeName(string $name): string
    {
        return strtr($name, '()', '[]');
    }

    /**
     * @param array{string, string} $patterns
     */
    private static function formatName(string $language, ?string $script, ?string $region, array $patterns): string
    {
        if (null === $extra = $script ?? $region) {
            return $language;
        }

        if (null !== $script && null !== $region) {
            $extra = strtr($patterns[1], ['{0}' => $script, '{1}' => $region]);
        }

        return strtr($patterns[0], ['{0}' => $language, '{1}' => $extra]);
    }

    /**
     * Splits the locales that can have a name into their language, script and region subtags.
     *
     * @return array<string, array{string, ?string, ?string}>
     */
    private static function getSubtags(): array
    {
        if (isset(self::$subtags)) {
            return self::$subtags;
        }

        self::$subtags = [];

        foreach (self::getLocales() as $locale) {
            if (preg_match('/^([a-z]{2,3})(?:_([A-Z][a-z]{3}))?(?:_([A-Z]{2}|\d{3}))?$/', $locale, $m)) {
                self::$subtags[$locale] = [$m[1], ($m[2] ?? '') ?: null, ($m[3] ?? '') ?: null];
            }
        }

        return self::$subtags;
    }
}
