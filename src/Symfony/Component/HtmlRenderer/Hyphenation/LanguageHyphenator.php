<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HtmlRenderer\Hyphenation;

/**
 * The hyphenator of a stylesheet for the text of one language, the breaks of the words it saw kept.
 *
 * @internal
 */
final class LanguageHyphenator
{
    /** Longer words, like a sequence of letters that is no word, are not hyphenated */
    public const MAX_LENGTH = 100;

    private const CACHE_SIZE = 4096;

    /** @var array<string, list<int>> */
    private array $cache = [];

    public function __construct(
        private readonly HyphenatorInterface $hyphenator,
        public readonly string $language,
    ) {
    }

    /**
     * Returns where a word can break: the number of characters before each break, inside the word.
     *
     * @return list<int>
     */
    public function hyphenate(string $word): array
    {
        if (isset($this->cache[$word])) {
            return $this->cache[$word];
        }
        $length = mb_strlen($word);
        $breaks = [];
        if ($length <= self::MAX_LENGTH) {
            preg_match_all('/\X/u', $word, $matches);
            $boundaries = [];
            $offset = 0;
            foreach ($matches[0] as $grapheme) {
                $offset += mb_strlen($grapheme);
                $boundaries[$offset] = true;
            }
            // Only breaks inside the word and between graphemes, once each and in order.
            foreach ($this->hyphenator->hyphenate($word, $this->language) as $at) {
                if (\is_int($at) && $at > 0 && $at < $length && isset($boundaries[$at])) {
                    $breaks[$at] = $at;
                }
            }
            ksort($breaks);
        }
        if (\count($this->cache) >= self::CACHE_SIZE) {
            $this->cache = [];
        }

        return $this->cache[$word] = array_values($breaks);
    }
}
