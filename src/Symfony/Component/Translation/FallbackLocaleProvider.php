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
 * @author Matthias Pigulla <mp@webfactory.de>
 */
class FallbackLocaleProvider implements FallbackLocaleProviderInterface
{
    /**
     * @var string[]
     *
     * List of fallback locales to add _after_ the ones derived from ICU information.
     */
    private array $ultimateFallbackLocales;

    private ?array $parentLocales = null;

    /**
     * @param string[] $ultimateFallbackLocales
     *
     * @throws InvalidArgumentException If a locale contains invalid characters
     */
    public function __construct(array $ultimateFallbackLocales = [])
    {
        foreach ($ultimateFallbackLocales as $locale) {
            LocaleValidator::validate($locale);
        }

        $this->ultimateFallbackLocales = $ultimateFallbackLocales;
    }

    /**
     * @return string[]
     */
    public function getUltimateFallbackLocales(): array
    {
        return $this->ultimateFallbackLocales;
    }

    /**
     * @return string[]
     */
    public function computeFallbackLocales(string $locale): array
    {
        LocaleValidator::validate($locale);

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

        foreach ($this->ultimateFallbackLocales as $fallback) {
            if ($fallback === $originLocale) {
                continue;
            }

            $locales[] = $fallback;
        }

        return array_unique($locales);
    }
}
