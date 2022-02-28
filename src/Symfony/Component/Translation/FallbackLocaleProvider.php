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

/**
 * Derives fallback locales based on ICU parent locale information, by shortening locale
 * sub tags and ultimately by going through a list of configured fallback locales.
 *
 * @author Matthias Pigulla <mp@webfactory.de>
 */
class FallbackLocaleProvider implements FallbackLocaleProviderInterface
{
    /**
     * @var string[]
     */
    private array $fallbackLocales = [];

    private array $parentLocales;

    public function setFallbackLocales(array $locales): void
    {
        foreach ($locales as $locale) {
            LocaleValidator::assertValidLocale($locale);
        }

        $this->fallbackLocales = $locales;
    }

    /**
     * @internal
     */
    public function getFallbackLocales(): array
    {
        return $this->fallbackLocales;
    }

    /**
     * @return string[]
     */
    public function computeFallbackLocales(string $locale): array
    {
        LocaleValidator::assertValidLocale($locale);

        $this->parentLocales ??= json_decode(file_get_contents(__DIR__.'/Resources/data/parents.json'), true);

        $originLocale = $locale;
        $locales = [];

        while ($locale) {
            $parent = $this->parentLocales[$locale] ?? null;

            if ($parent) {
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
                $locales[] = $locale;
            }
        }

        foreach ($this->fallbackLocales as $fallback) {
            if ($fallback === $originLocale) {
                continue;
            }

            $locales[] = $fallback;
        }

        return array_unique($locales);
    }
}
