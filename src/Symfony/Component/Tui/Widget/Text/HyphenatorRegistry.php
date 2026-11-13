<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Tui\Widget\Text;

/**
 * Registry for the hyphenation patterns of languages, like CSS hyphens: auto, by the lang of the text.
 *
 * The patterns of English (en-us), French (fr), German (de-1996) and
 * Spanish (es), from hyph-utf8, are registered by default. Others can be
 * registered by language tag, with the .pat.txt file of their patterns:
 *
 *     $registry = new HyphenatorRegistry();
 *     $registry->register('it', '/path/to/hyph-it.pat.txt');
 *
 * @experimental
 */
final class HyphenatorRegistry
{
    private const string BUNDLED_DIR = __DIR__.'/hyphenation';
    public const array BUNDLED_LANGUAGES = ['en-us', 'fr', 'de-1996', 'es'];

    /** The languages of the bundled patterns, by their primary tag */
    private const array DEFAULTS = ['en' => 'en-us', 'de' => 'de-1996'];

    /** @var array<string, string> language → file path */
    private array $paths = [];

    /** @var array<string, Hyphenator> language → loaded patterns (cache) */
    private array $hyphenators = [];

    public function __construct()
    {
        foreach (self::BUNDLED_LANGUAGES as $language) {
            $this->paths[$language] = self::BUNDLED_DIR.'/'.$language.'.php';
        }
    }

    /**
     * Registers the patterns of a language, a .pat.txt file (its exceptions in the .hyp.txt file next to it) or a .php
     * file returning them, like the bundled ones.
     *
     * @return $this
     */
    public function register(string $language, string $path): static
    {
        $language = strtolower($language);
        $this->paths[$language] = $path;
        unset($this->hyphenators[$language]);

        return $this;
    }

    /**
     * Returns the patterns of a language tag: those of the tag, else of its primary language, like fr for fr-CA.
     */
    public function get(string $language): ?Hyphenator
    {
        if (null === $language = $this->resolve($language)) {
            return null;
        }

        return $this->hyphenators[$language] ??= Hyphenator::load($this->paths[$language]);
    }

    public function has(string $language): bool
    {
        return null !== $this->resolve($language);
    }

    /**
     * @return string[]
     */
    public function getLanguages(): array
    {
        return array_keys($this->paths);
    }

    private function resolve(string $language): ?string
    {
        $language = strtolower(str_replace('_', '-', trim($language)));
        if ('' === $language) {
            return null;
        }
        $primary = explode('-', $language)[0];
        foreach ([$language, self::DEFAULTS[$language] ?? null, $primary, self::DEFAULTS[$primary] ?? null] as $candidate) {
            if (null !== $candidate && isset($this->paths[$candidate])) {
                return $candidate;
            }
        }

        return null;
    }
}
