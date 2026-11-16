<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\TuiHtml\Tests\Fixtures;

use Symfony\Component\TuiHtml\Hyphenation\HyphenatorInterface;

/**
 * Breaks the words it lists, by language: what a hyphenator with dictionaries does, without one installed.
 */
final class WordListHyphenator implements HyphenatorInterface
{
    /** @var list<array{string, string}> */
    public array $asked = [];

    /**
     * @param array<string, array<string, list<int>>> $words The breaks of each word, by primary language subtag
     */
    public function __construct(private readonly array $words)
    {
    }

    /**
     * The breaks of the words of the Compat fixtures, as TeX patterns give them.
     */
    public static function forFixtures(): self
    {
        return new self([
            'en' => [
                'Automatic' => [2, 4], 'hyphenation' => [2, 6], 'extraordinarily' => [2, 5, 7, 9, 12], 'English' => [3], 'Authored' => [2],
                'super' => [2], 'califragilistic' => [3, 8, 12], 'broken' => [3], 'Manual' => [3], 'without' => [4], 'hyphens' => [2],
                'Limits' => [3], 'incomprehensibilities' => [2, 5, 8, 11, 13, 16, 17], 'example' => [2, 5],
            ],
            'fr' => [
                'informatique' => [2, 5, 7], 'révolutionne' => [2, 4, 6], 'développement' => [2, 4, 7, 9], 'communication' => [3, 5, 7, 9],
                'anticonstitutionnellement' => [2, 4, 8, 10, 12, 16, 19, 21],
            ],
            'de' => [
                'Silbentrennung' => [3, 6, 10], 'Donaudampfschifffahrtsgesellschaften' => [2, 5, 10, 16, 22, 24, 28, 33], 'Änderungsmöglichkeiten' => [2, 5, 9, 12, 16, 19],
            ],
            'es' => [
                'computadora' => [5, 9], 'calcula' => [3, 5], 'rápidamente' => [2, 4, 6, 9], 'operaciones' => [3, 5, 8],
            ],
        ]);
    }

    public function hyphenate(string $word, string $language): array
    {
        $this->asked[] = [$word, $language];

        return $this->words[explode('-', $language)[0]][$word] ?? [];
    }
}
