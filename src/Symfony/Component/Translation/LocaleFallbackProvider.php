<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Translation;

use Symfony\Component\Translation\Exception\InvalidArgumentException;

/**
 * Derives fallback locales based on ICU parent locale information, by shortening locale
 * sub tags and ultimately by going through a list of configured fallback locales.
 *
 * Also provides locale string validation.
 *
 * @author Matthias Pigulla <mp@webfactory.de>
 */
final class LocaleFallbackProvider
{
    /**
     * @param string[] $localeFallbacks List of fallback locales to add _after_ the ones derived from ICU information
     *
     * @throws InvalidArgumentException If a locale contains invalid characters
     */
    public function __construct(
        private array $localeFallbacks = [],
    ) {
        foreach ($localeFallbacks as $locale) {
            self::validateLocale($locale);
        }
    }

    /**
     * @return string[]
     */
    public function computeFallbackLocales(string $locale): array
    {
        self::validateLocale($locale);

        static $parentLocales;
        $parentLocales ??= require __DIR__.'/Resources/data/parents.php';

        $originLocale = $locale;
        $locales = [];

        while ($locale) {
            if ($parent = $parentLocales[$locale] ?? null) {
                $locale = 'root' !== $parent ? $parent : null;
            } elseif (\function_exists('locale_parse')) {
                $localeSubTags = locale_parse($locale);
                $locale = null;
                if (1 < \count($localeSubTags)) {
                    array_pop($localeSubTags);
                    $locale = locale_compose($localeSubTags) ?: null;
                }
            } elseif ($i = strrpos($locale, '_') ?: strrpos($locale, '-')) {
                $locale = substr($locale, 0, $i);
            } else {
                $locale = null;
            }

            if (null !== $locale) {
                $locales[$locale] = $locale;
            }
        }

        foreach ($this->localeFallbacks as $fallback) {
            if ($fallback === $originLocale) {
                continue;
            }

            $locales[$fallback] = $fallback;
        }

        return array_keys($locales);
    }

    /**
     * Asserts that the locale is valid, throws an Exception if not.
     *
     * @throws InvalidArgumentException If the locale contains invalid characters
     */
    public static function validateLocale(string $locale): void
    {
        if (!preg_match('/^[a-z0-9@_\.\-]*$/i', $locale)) {
            throw new InvalidArgumentException(\sprintf('Invalid "%s" locale.', $locale));
        }
    }
}
