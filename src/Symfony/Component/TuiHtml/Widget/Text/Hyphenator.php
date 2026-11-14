<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\TuiHtml\Widget\Text;

use Symfony\Component\Tui\Exception\InvalidArgumentException;

/**
 * Finds where words can be hyphenated, with the patterns of Liang's algorithm (those of TeX).
 *
 * A pattern is letters with digits between them, like "hy3ph": an odd
 * digit is a place a word can break, an even one a place it cannot, the
 * highest digit of all the patterns found in the word winning. A dot marks
 * the start or the end of the word.
 *
 * @experimental
 */
final class Hyphenator
{
    private const CACHE_SIZE = 4096;
    /** Longer than any word: a run of letters this long is not hyphenated, nor costs the time to try */
    public const MAX_LENGTH = 100;
    /** @var array<string, string>|null The letters of each pattern → its digits, one more than the letters */
    private ?array $patterns = null;
    private int $longest = 0;
    /** @var array<string, list<int>> */
    private array $exceptions = [];
    /** @var array<string, list<int>> */
    private array $cache = [];

    /**
     * @param string $source          The patterns, separated by white space, like the .pat.txt files of hyph-utf8
     * @param string $exceptionSource Words with their hyphens, like "ta-ble", separated by white space, like the .hyp.txt files
     * @param int    $leftMin         The fewest characters before a break
     * @param int    $rightMin        The fewest characters after a break
     */
    public function __construct(private readonly string $source, private readonly string $exceptionSource = '', public readonly int $leftMin = 2, public readonly int $rightMin = 2)
    {
    }

    /**
     * Loads the patterns of a .php file of this component (an array of left, right, patterns and exceptions) or of a
     * .pat.txt file, its exceptions from the .hyp.txt file next to it, if any.
     */
    public static function load(string $path): self
    {
        if (str_ends_with($path, '.php')) {
            $data = (static fn (string $path) => require $path)($path);
            // Compiled, like the files of this component: the letters of the patterns and their digits, a line each,
            // which two explode() and an array_combine() turn into the table, no pattern to parse
            if (\is_array($data) && \is_string($data['letters'] ?? null) && \is_string($data['digits'] ?? null)) {
                $hyphenator = new self('', '', (int) ($data['left'] ?? 2), (int) ($data['right'] ?? 2));
                $letters = explode("\n", $data['letters']);
                $digits = explode("\n", $data['digits']);
                if (\count($letters) !== \count($digits)) {
                    throw new InvalidArgumentException(\sprintf('The hyphenation patterns file "%s" does not have as many digits as letters.', $path));
                }
                $hyphenator->patterns = array_combine($letters, $digits);
                $hyphenator->longest = (int) ($data['longest'] ?? 0);
                $hyphenator->exceptions = \is_array($data['exceptions'] ?? null) ? $data['exceptions'] : [];

                return $hyphenator;
            }
            if (!\is_array($data) || !\is_string($data['patterns'] ?? null)) {
                throw new InvalidArgumentException(\sprintf('The hyphenation patterns file "%s" must return an array with "patterns".', $path));
            }

            return new self($data['patterns'], (string) ($data['exceptions'] ?? ''), (int) ($data['left'] ?? 2), (int) ($data['right'] ?? 2));
        }
        if (!is_file($path) || false === $patterns = file_get_contents($path)) {
            throw new InvalidArgumentException(\sprintf('The hyphenation patterns file "%s" cannot be read.', $path));
        }
        $exceptions = preg_replace('/\.pat\.txt$/', '.hyp.txt', $path);
        $exceptions = $exceptions !== $path && is_file($exceptions) ? (string) file_get_contents($exceptions) : '';

        return new self($patterns, $exceptions);
    }

    /**
     * Returns where a word can break: the number of characters before each break.
     *
     * @return list<int>
     */
    public function hyphenate(string $word): array
    {
        if (mb_strlen($word) > self::MAX_LENGTH) {
            return [];
        }
        $lower = mb_strtolower($word);
        if (isset($this->cache[$lower])) {
            return $this->cache[$lower];
        }
        $characters = mb_str_split($lower);
        $length = \count($characters);
        if ($length < $this->leftMin + $this->rightMin || \count(mb_str_split($word)) !== $length) {
            return [];
        }
        if (null === $this->patterns) {
            $this->compile();
        }
        if (isset($this->exceptions[$lower])) {
            $breaks = $this->exceptions[$lower];
        } else {
            $text = ['.', ...$characters, '.'];
            $points = array_fill(0, $length + 3, 0);
            for ($start = 0; $start < $length + 2; ++$start) {
                $letters = '';
                for ($end = $start; $end < min($length + 2, $start + $this->longest); ++$end) {
                    $letters .= $text[$end];
                    if (null === $digits = $this->patterns[$letters] ?? null) {
                        continue;
                    }
                    for ($k = 0, $count = \strlen($digits); $k < $count; ++$k) {
                        if ((int) $digits[$k] > $points[$start + $k]) {
                            $points[$start + $k] = (int) $digits[$k];
                        }
                    }
                }
            }
            // The point before a character of the word is before its index plus the dot
            $breaks = [];
            for ($at = 1; $at < $length; ++$at) {
                if (1 === $points[$at + 1] % 2) {
                    $breaks[] = $at;
                }
            }
        }
        $breaks = array_values(array_filter($breaks, fn (int $at) => $at >= $this->leftMin && $length - $at >= $this->rightMin));
        if (\count($this->cache) >= self::CACHE_SIZE) {
            $this->cache = [];
        }

        return $this->cache[$lower] = $breaks;
    }

    /**
     * The patterns and exceptions compiled, as update-hyphenation.php writes them for load().
     *
     * @internal
     *
     * @return array{patterns: array<string, string>, longest: int, exceptions: array<string, list<int>>}
     */
    public function compiled(): array
    {
        if (null === $this->patterns) {
            $this->compile();
        }

        return ['patterns' => $this->patterns ?? [], 'longest' => $this->longest, 'exceptions' => $this->exceptions];
    }

    private function compile(): void
    {
        $this->patterns = [];
        foreach (preg_split('/\s+/u', trim($this->source)) ?: [] as $pattern) {
            if ('' === $pattern) {
                continue;
            }
            $letters = preg_replace('/\d/', '', $pattern) ?? $pattern;
            $digits = '';
            $pending = '0';
            foreach (mb_str_split($pattern) as $character) {
                if (ctype_digit($character)) {
                    $pending = $character;
                    continue;
                }
                $digits .= $pending;
                $pending = '0';
            }
            $this->patterns[$letters] = $digits.$pending;
            $this->longest = max($this->longest, mb_strlen($letters));
        }
        foreach (preg_split('/\s+/u', trim($this->exceptionSource)) ?: [] as $exception) {
            if ('' === $exception) {
                continue;
            }
            $breaks = [];
            $at = 0;
            foreach (mb_str_split(mb_strtolower($exception)) as $character) {
                if ('-' === $character) {
                    $breaks[] = $at;
                } else {
                    ++$at;
                }
            }
            $this->exceptions[str_replace('-', '', mb_strtolower($exception))] = $breaks;
        }
    }
}
