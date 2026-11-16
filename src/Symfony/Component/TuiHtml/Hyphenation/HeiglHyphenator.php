<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\TuiHtml\Hyphenation;

use Org\Heigl\Hyphenator\Hyphenator;
use Symfony\Component\TuiHtml\Exception\LogicException;

/**
 * Hyphenates with the dictionaries of org_heigl/hyphenator, about 40 languages.
 *
 * A language tag picks the dictionary of its language and region ("de-ch" de_CH), or else of its language ("fr"), or
 * else the main one of its language ("de" de_DE, "en" en_US, "cs" cs_CZ); a language without one is not hyphenated.
 *
 * @experimental
 */
final class HeiglHyphenator implements HyphenatorInterface
{
    /** @var array<string, string|null> The dictionary of each language tag asked, null without one */
    private array $locales = [];

    /** @var array<string, Hyphenator> */
    private array $hyphenators = [];

    /** @var list<string>|null */
    private ?array $dictionaries = null;

    /**
     * @param string|null $homePath The directory holding files/dictionaries, the one of the package by default
     */
    public function __construct(
        private readonly ?string $homePath = null,
        private readonly int $leftMin = 2,
        private readonly int $rightMin = 2,
    ) {
        if (!class_exists(Hyphenator::class)) {
            throw new LogicException(\sprintf('You cannot use "%s" as the org_heigl/hyphenator package is not installed. Try running "composer require org_heigl/hyphenator".', self::class));
        }
    }

    public function hyphenate(string $word, string $language): array
    {
        $locale = \array_key_exists($language, $this->locales) ? $this->locales[$language] : $this->locales[$language] = $this->locale($language);
        if (null === $locale) {
            return [];
        }
        $hyphenator = $this->hyphenators[$locale] ??= $this->create($locale);
        $hyphenated = $hyphenator->hyphenate($word);
        // A word it changed otherwise than by adding soft hyphens is not broken
        if (!\is_string($hyphenated) || str_replace("\u{AD}", '', $hyphenated) !== $word) {
            return [];
        }
        $breaks = [];
        $at = 0;
        foreach (explode("\u{AD}", $hyphenated) as $i => $part) {
            if ($i > 0) {
                $breaks[] = $at;
            }
            $at += mb_strlen($part);
        }

        return $breaks;
    }

    /**
     * The supported languages, as locales like "de_DE" or "fr".
     *
     * @return list<string>
     */
    public function getLocales(): array
    {
        if (null === $this->dictionaries) {
            $this->dictionaries = [];
            foreach (glob($this->directory().'/*.ini') ?: [] as $file) {
                if (0 < filesize($file)) {
                    $this->dictionaries[] = basename($file, '.ini');
                }
            }
            sort($this->dictionaries);
        }

        return $this->dictionaries;
    }

    private function locale(string $language): ?string
    {
        $subtags = explode('-', strtolower(str_replace('_', '-', $language)));
        $primary = $subtags[0];
        $byLowercase = [];
        foreach ($this->getLocales() as $locale) {
            $byLowercase[strtolower($locale)] = $locale;
        }
        // Language and region, the language alone, the region named like the language, the main region of English
        foreach ([isset($subtags[1]) ? $primary.'_'.$subtags[1] : null, $primary, $primary.'_'.$primary, 'en' === $primary ? 'en_us' : null] as $candidate) {
            if (null !== $candidate && isset($byLowercase[$candidate])) {
                return $byLowercase[$candidate];
            }
        }
        // Else the first region of the language, like cs_CZ for "cs"
        foreach ($byLowercase as $lowercase => $locale) {
            if (str_starts_with($lowercase, $primary.'_')) {
                return $locale;
            }
        }

        return null;
    }

    private function create(string $locale): Hyphenator
    {
        $hyphenator = Hyphenator::factory($this->homePath, $locale);
        $hyphenator->getOptions()
            ->setHyphen("\u{AD}")
            ->setLeftMin($this->leftMin)
            ->setRightMin($this->rightMin)
            // hyphenate-limit-chars decides the shortest word broken
            ->setMinWordLength($this->leftMin + $this->rightMin);

        return $hyphenator;
    }

    private function directory(): string
    {
        return ($this->homePath ?? Hyphenator::getDefaultHomePath()).'/files/dictionaries';
    }
}
