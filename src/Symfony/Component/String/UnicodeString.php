<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\String;

use Symfony\Component\String\Exception\ExceptionInterface;
use Symfony\Component\String\Exception\InvalidArgumentException;

/**
 * Represents a string of Unicode grapheme clusters encoded as UTF-8.
 *
 * A letter followed by combining characters (accents typically) form what Unicode defines
 * as a grapheme cluster: a character as humans mean it in written texts. This class knows
 * about the concept and won't split a letter apart from its combining accents. It also
 * ensures all string comparisons happen on their canonically-composed representation,
 * ignoring e.g. the order in which accents are listed when a letter has many of them.
 *
 * @see https://unicode.org/reports/tr15/
 *
 * @author Nicolas Grekas <p@tchwork.com>
 * @author Hugo Hamon <hugohamon@neuf.fr>
 *
 * @throws ExceptionInterface
 */
class UnicodeString extends AbstractUnicodeString
{
    private static array $trimChars = [];

    public function __construct(string $string = '')
    {
        if ('' === $string || normalizer_is_normalized($this->string = $string)) {
            return;
        }

        if (false === $string = normalizer_normalize($string)) {
            throw new InvalidArgumentException('Invalid UTF-8 string.');
        }

        $this->string = $string;
    }

    public function append(string ...$suffix): static
    {
        $str = clone $this;
        $str->string = $this->string.(1 >= \count($suffix) ? ($suffix[0] ?? '') : implode('', $suffix));

        if (normalizer_is_normalized($str->string)) {
            return $str;
        }

        if (false === $string = normalizer_normalize($str->string)) {
            throw new InvalidArgumentException('Invalid UTF-8 string.');
        }

        $str->string = $string;

        return $str;
    }

    public function chunk(int $length = 1): array
    {
        if (1 > $length) {
            throw new InvalidArgumentException('The chunk length must be greater than zero.');
        }

        if ('' === $this->string) {
            return [];
        }

        if (\strlen($this->string) <= $length) {
            return [clone $this];
        }

        $rx = '/(';
        while (65535 < $length) {
            $rx .= '\X{65535}';
            $length -= 65535;
        }
        $rx .= '\X{'.$length.'})/u';

        $str = clone $this;
        $chunks = [];

        foreach (preg_split($rx, $this->string, -1, \PREG_SPLIT_DELIM_CAPTURE | \PREG_SPLIT_NO_EMPTY) as $chunk) {
            $str->string = $chunk;
            $chunks[] = clone $str;
        }

        return $chunks;
    }

    public function endsWith(string|iterable|AbstractString $suffix): bool
    {
        if ($suffix instanceof AbstractString) {
            $suffix = $suffix->string;
        } elseif (!\is_string($suffix)) {
            return parent::endsWith($suffix);
        }

        $form = null === $this->ignoreCase ? \Normalizer::NFD : \Normalizer::NFC;
        normalizer_is_normalized($suffix, $form) ?: $suffix = normalizer_normalize($suffix, $form);

        if ('' === $suffix || false === $suffix) {
            return false;
        }

        if (false === $grapheme = grapheme_extract($this->string, \strlen($suffix), \GRAPHEME_EXTR_MAXBYTES, \strlen($this->string) - \strlen($suffix))) {
            $grapheme = '';
        }

        if ($this->ignoreCase ? 0 !== mb_stripos($grapheme, $suffix, 0, 'UTF-8') : $suffix !== $grapheme) {
            return false;
        }

        return $this->isGraphemeBoundary(\strlen($this->string) - \strlen($grapheme));
    }

    public function equalsTo(string|iterable|AbstractString $string): bool
    {
        if ($string instanceof AbstractString) {
            $string = $string->string;
        } elseif (!\is_string($string)) {
            return parent::equalsTo($string);
        }

        $form = null === $this->ignoreCase ? \Normalizer::NFD : \Normalizer::NFC;
        normalizer_is_normalized($string, $form) ?: $string = normalizer_normalize($string, $form);

        if ('' !== $string && false !== $string && $this->ignoreCase) {
            return \strlen($string) === \strlen($this->string) && 0 === mb_stripos($this->string, $string, 0, 'UTF-8');
        }

        return $string === $this->string;
    }

    public function indexOf(string|iterable|AbstractString $needle, int $offset = 0): ?int
    {
        if ($needle instanceof AbstractString) {
            $needle = $needle->string;
        } elseif (!\is_string($needle)) {
            return parent::indexOf($needle, $offset);
        }

        $form = null === $this->ignoreCase ? \Normalizer::NFD : \Normalizer::NFC;
        normalizer_is_normalized($needle, $form) ?: $needle = normalizer_normalize($needle, $form);

        if ('' === $needle || false === $needle) {
            return null;
        }

        try {
            $i = $this->ignoreCase ? grapheme_stripos($this->string, $needle, $offset) : grapheme_strpos($this->string, $needle, $offset);
        } catch (\ValueError) {
            return null;
        }

        return false === $i ? null : $i;
    }

    public function indexOfLast(string|iterable|AbstractString $needle, int $offset = 0): ?int
    {
        if ($needle instanceof AbstractString) {
            $needle = $needle->string;
        } elseif (!\is_string($needle)) {
            return parent::indexOfLast($needle, $offset);
        }

        $form = null === $this->ignoreCase ? \Normalizer::NFD : \Normalizer::NFC;
        normalizer_is_normalized($needle, $form) ?: $needle = normalizer_normalize($needle, $form);

        if ('' === $needle || false === $needle) {
            return null;
        }

        $string = $this->string;

        if (0 > $offset) {
            // ICU's grapheme_strrpos() disagrees with mb_strrpos() for multi-grapheme needles at
            // negative offsets: it truncates the haystack instead of bounding the match start, so
            // overlapping matches resolve too far left. bugs.php.net/74264 only fixed the single-grapheme case
            if (0 > $offset += grapheme_strlen($needle)) {
                $string = grapheme_substr($string, 0, max(-2147483648, $offset));
            }
            $offset = 0;
        }

        try {
            $i = $this->ignoreCase ? grapheme_strripos($string, $needle, $offset) : grapheme_strrpos($string, $needle, $offset);
        } catch (\ValueError) {
            return null;
        }

        return false === $i ? null : $i;
    }

    public function join(array $strings, ?string $lastGlue = null): static
    {
        $str = parent::join($strings, $lastGlue);
        normalizer_is_normalized($str->string) ?: $str->string = normalizer_normalize($str->string);

        return $str;
    }

    public function length(): int
    {
        return grapheme_strlen($this->string);
    }

    public function normalize(int $form = self::NFC): static
    {
        $str = clone $this;

        if (\in_array($form, [self::NFC, self::NFKC], true)) {
            normalizer_is_normalized($str->string, $form) ?: $str->string = normalizer_normalize($str->string, $form);
        } elseif (!\in_array($form, [self::NFD, self::NFKD], true)) {
            throw new InvalidArgumentException('Unsupported normalization form.');
        } elseif (!normalizer_is_normalized($str->string, $form)) {
            $str->string = normalizer_normalize($str->string, $form);
            $str->ignoreCase = null;
        }

        return $str;
    }

    public function prepend(string ...$prefix): static
    {
        $str = clone $this;
        $str->string = (1 >= \count($prefix) ? ($prefix[0] ?? '') : implode('', $prefix)).$this->string;

        if (normalizer_is_normalized($str->string)) {
            return $str;
        }

        if (false === $string = normalizer_normalize($str->string)) {
            throw new InvalidArgumentException('Invalid UTF-8 string.');
        }

        $str->string = $string;

        return $str;
    }

    public function replace(string $from, string $to): static
    {
        $str = clone $this;
        normalizer_is_normalized($from) ?: $from = normalizer_normalize($from);

        if (!$this->ignoreCase && \is_string($from) && !str_contains($str->string, $from)) {
            return $str;
        }

        if ('' !== $from && false !== $from) {
            $tail = $str->string;
            $result = '';

            while ('' !== $tail && false !== $i = $this->search($tail, $from, $length)) {
                $result .= substr($tail, 0, $i).$to;
                $tail = substr($tail, $i + $length);
            }

            $str->string = $result.$tail;

            if (normalizer_is_normalized($str->string)) {
                return $str;
            }

            if (false === $string = normalizer_normalize($str->string)) {
                throw new InvalidArgumentException('Invalid UTF-8 string.');
            }

            $str->string = $string;
        }

        return $str;
    }

    /**
     * @param-immediately-invoked-callable $to
     */
    public function replaceMatches(string $fromRegexp, string|callable $to): static
    {
        $str = parent::replaceMatches($fromRegexp, $to);
        normalizer_is_normalized($str->string) ?: $str->string = normalizer_normalize($str->string);

        return $str;
    }

    public function slice(int $start = 0, ?int $length = null): static
    {
        $str = clone $this;

        // grapheme_substr() accepts only 32-bit offsets and lengths
        $start = max(-2147483648, min($start, 2147483647));
        $length = max(-2147483648, min($length ?? 2147483647, 2147483647));
        $str->string = (string) grapheme_substr($this->string, $start, $length);

        return $str;
    }

    public function splice(string $replacement, int $start = 0, ?int $length = null): static
    {
        $str = clone $this;

        $start = $start ? \strlen(grapheme_substr($this->string, 0, max(-2147483648, min($start, 2147483647)))) : 0;
        $length = $length ? \strlen(grapheme_substr(substr($this->string, $start), 0, max(-2147483648, min($length, 2147483647)))) : $length;
        $str->string = substr_replace($this->string, $replacement, $start, $length ?? 2147483647);

        if (normalizer_is_normalized($str->string)) {
            return $str;
        }

        if (false === $string = normalizer_normalize($str->string)) {
            throw new InvalidArgumentException('Invalid UTF-8 string.');
        }

        $str->string = $string;

        return $str;
    }

    public function split(string $delimiter, ?int $limit = null, ?int $flags = null): array
    {
        if (1 > $limit ??= 2147483647) {
            throw new InvalidArgumentException('Split limit must be a positive integer.');
        }

        if ('' === $delimiter) {
            throw new InvalidArgumentException('Split delimiter is empty.');
        }

        if (null !== $flags) {
            return parent::split($delimiter.'u', $limit, $flags);
        }

        normalizer_is_normalized($delimiter) ?: $delimiter = normalizer_normalize($delimiter);

        if (false === $delimiter) {
            throw new InvalidArgumentException('Split delimiter is not a valid UTF-8 string.');
        }

        $str = clone $this;
        $tail = $this->string;
        $chunks = [];

        while (1 < $limit && false !== $i = $this->search($tail, $delimiter, $length)) {
            $str->string = substr($tail, 0, $i);
            $chunks[] = clone $str;
            $tail = substr($tail, $i + $length);
            --$limit;
        }

        $str->string = $tail;
        $chunks[] = clone $str;

        return $chunks;
    }

    public function startsWith(string|iterable|AbstractString $prefix): bool
    {
        if ($prefix instanceof AbstractString) {
            $prefix = $prefix->string;
        } elseif (!\is_string($prefix)) {
            return parent::startsWith($prefix);
        }

        $form = null === $this->ignoreCase ? \Normalizer::NFD : \Normalizer::NFC;
        normalizer_is_normalized($prefix, $form) ?: $prefix = normalizer_normalize($prefix, $form);

        if ('' === $prefix || false === $prefix) {
            return false;
        }

        if (false === $grapheme = grapheme_extract($this->string, \strlen($prefix), \GRAPHEME_EXTR_MAXBYTES)) {
            $grapheme = '';
        }

        if ($this->ignoreCase) {
            return 0 === mb_stripos($grapheme, $prefix, 0, 'UTF-8');
        }

        return $prefix === $grapheme;
    }

    public function trim(string $chars = " \t\n\r\0\x0B\x0C\u{A0}\u{FEFF}"): static
    {
        return $this->trimGraphemes($chars, true, true);
    }

    public function trimEnd(string $chars = " \t\n\r\0\x0B\x0C\u{A0}\u{FEFF}"): static
    {
        return $this->trimGraphemes($chars, false, true);
    }

    public function trimPrefix($prefix): static
    {
        if (\is_array($prefix) || $prefix instanceof \Traversable) {
            foreach ($prefix as $s) {
                $t = $this->trimPrefix($s);

                if ($t->string !== $this->string) {
                    return $t;
                }
            }

            return clone $this;
        }

        if ($prefix instanceof AbstractString) {
            $prefix = $prefix->string;
        } else {
            $prefix = (string) $prefix;
        }

        if (!normalizer_is_normalized($prefix, \Normalizer::NFC)) {
            $prefix = normalizer_normalize($prefix, \Normalizer::NFC);
        }

        $str = parent::trimPrefix($prefix);

        return $this->isGraphemeBoundary(\strlen($this->string) - \strlen($str->string)) ? $str : clone $this;
    }

    public function trimStart(string $chars = " \t\n\r\0\x0B\x0C\u{A0}\u{FEFF}"): static
    {
        return $this->trimGraphemes($chars, true, false);
    }

    public function trimSuffix($suffix): static
    {
        if (\is_array($suffix) || $suffix instanceof \Traversable) {
            foreach ($suffix as $s) {
                $t = $this->trimSuffix($s);

                if ($t->string !== $this->string) {
                    return $t;
                }
            }

            return clone $this;
        }

        if ($suffix instanceof AbstractString) {
            $suffix = $suffix->string;
        } else {
            $suffix = (string) $suffix;
        }

        if (!normalizer_is_normalized($suffix, \Normalizer::NFC)) {
            $suffix = normalizer_normalize($suffix, \Normalizer::NFC);
        }

        $str = parent::trimSuffix($suffix);

        return $this->isGraphemeBoundary(\strlen($str->string)) ? $str : clone $this;
    }

    public function __unserialize(array $data): void
    {
        if (($data['string'] ?? null) instanceof \Stringable || ($data["\0*\0string"] ?? null) instanceof \Stringable) {
            throw new \BadMethodCallException('Cannot unserialize '.__CLASS__);
        }

        $this->string = $data['string'] ?? $data["\0*\0string"];

        if (!\is_string($this->string)) {
            throw new \BadMethodCallException('Cannot unserialize '.__CLASS__);
        }

        normalizer_is_normalized($this->string) ?: $this->string = normalizer_normalize($this->string);
    }

    public function __clone()
    {
        if (null === $this->ignoreCase) {
            normalizer_is_normalized($this->string) ?: $this->string = normalizer_normalize($this->string);
        }

        $this->ignoreCase = false;
    }

    /**
     * Returns the byte offset of the first occurrence of $needle in $haystack and sets $length to its length in bytes.
     */
    private function search(string $haystack, string $needle, ?int &$length = null): int|false
    {
        if (false === $i = $this->ignoreCase ? grapheme_stripos($haystack, $needle) : grapheme_strpos($haystack, $needle)) {
            return false;
        }

        $offset = \strlen(grapheme_substr($haystack, 0, $i));
        $length = \strlen($needle);
        $match = substr($haystack, $offset, $length);

        // On non-ASCII strings, grapheme_strpos() ignores characters like U+200B.
        // Its match can then differ from $needle, or start after the first characters of $needle.
        // Without ext-intl, a match that starts inside a grapheme is reported at the next grapheme.
        if (!$this->ignoreCase) {
            if ($needle === $match) {
                return $offset;
            }

            $previous = $i ? \strlen(grapheme_substr($haystack, 0, $i - 1)) : 0;

            return strpos($haystack, $needle, max(0, min($offset + 1 - $length, $previous + 1)));
        }

        if (0 === strcasecmp($needle, $match) || (preg_match('//u', $match) && (
            mb_convert_case($needle, \MB_CASE_FOLD_SIMPLE, 'UTF-8') === mb_convert_case($match, \MB_CASE_FOLD_SIMPLE, 'UTF-8')
            || (0 === grapheme_stripos($needle, $match) && 0 === grapheme_stripos($match, $needle))
        ))) {
            return $offset;
        }

        $previous = $i ? mb_strlen(grapheme_substr($haystack, 0, $i - 1), 'UTF-8') : 0;
        $i = mb_strlen(substr($haystack, 0, $offset), 'UTF-8');

        if (false === $i = mb_stripos($haystack, $needle, max(0, min($i + 1 - mb_strlen($needle, 'UTF-8'), $previous + 1)), 'UTF-8')) {
            return false;
        }

        $length = \strlen(mb_substr($haystack, $i, mb_strlen($needle, 'UTF-8'), 'UTF-8'));

        return \strlen(mb_substr($haystack, 0, $i, 'UTF-8'));
    }

    private function isGraphemeBoundary(int $i): bool
    {
        $s = $this->string;

        if (0 >= $i || \strlen($s) <= $i) {
            return true;
        }

        if (0x80 > \ord($s[$i - 1])) {
            if (0x80 > \ord($s[$i])) {
                return "\r" !== $s[$i - 1] || "\n" !== $s[$i];
            }

            // Only a char from U+0300 can extend an ASCII char, e.g. a combining accent
            if (0xCC > \ord($s[$i])) {
                return true;
            }
        } elseif (0x80 > \ord($s[$i])) {
            for ($j = $i - 1; 0xC0 > \ord($s[$j]); --$j) {
            }

            // Only a char from U+0600 can be extended by an ASCII char, e.g. a prepended concatenation mark
            if (0xD8 > \ord($s[$j])) {
                return true;
            }
        }

        $j = self::findSegmentationStart($s, $i - 1, 0);

        return $i - $j === \strlen(grapheme_extract($s, $i - $j, \GRAPHEME_EXTR_MAXBYTES, $j));
    }

    /**
     * Returns the offset of the closest char at or before $i, but not before $min, that grapheme clusters can be segmented from.
     */
    private static function findSegmentationStart(string $s, int $i, int $min): int
    {
        // Segmenting from a char below U+0300 or between U+4000 and U+9FFF gives the same boundaries after it as segmenting from the start
        while ($min < $i && 0x80 <= ($c = \ord($s[$i])) && (0xC2 > $c || 0xCC <= $c) && (0xE4 > $c || 0xEA <= $c)) {
            --$i;
        }

        return $i;
    }

    private function trimGraphemes(string $chars, bool $start, bool $end): static
    {
        if (" \t\n\r\0\x0B\x0C\u{A0}\u{FEFF}" === $chars) {
            $native = " \t\n\r\0\x0B\x0C";
            $rx = " \t\n\r\\000\x0B\x0C\u{A0}\u{FEFF}";
            $graphemes = $ranges = null;
        } elseif ('' === $chars) {
            return clone $this;
        } elseif (!str_contains($chars, '..') && !preg_match('/[\x80-\xFF]/', $chars)) {
            $native = $chars;
            $rx = preg_quote($chars);
            $graphemes = $ranges = null;
        } else {
            if (100 <= \count(self::$trimChars)) {
                self::$trimChars = [];
            }

            $native = null;
            [$rx, $graphemes, $ranges] = self::$trimChars[$chars] ??= self::parseTrimChars($chars);
        }

        $s = $this->string;
        $str = clone $this;

        if (null !== $native) {
            $t = $start ? ($end ? trim($s, $native) : ltrim($s, $native)) : rtrim($s, $native);

            // ASCII edges other than a split CRLF mean that no grapheme cluster was cut
            if ('' === $t || ((!$start || (0x80 > \ord($t[0]) && "\n" !== $t[0])) && (!$end || (0x80 > \ord($t[-1]) && "\r" !== $t[-1])))) {
                $str->string = $t;

                return $str;
            }
        }

        $i = 0;
        $j = \strlen($s);

        if (null === $graphemes) {
            // No char in $chars joins its neighbors: trim code points, then check that both cuts are grapheme boundaries
            if (!$end) {
                $i = preg_match("{^[$rx]++}u", $s, $m) ? \strlen($m[0]) : 0;
            } else {
                preg_match($start ? "{^[$rx]*+\K(?:.*[^$rx])?}su" : "{^(?:.*[^$rx])?}su", $s, $m, \PREG_OFFSET_CAPTURE);
                $i = $m[0][1];
                $j = $i + \strlen($m[0][0]);
            }

            if (0 < $i && $i < $j) {
                $k = $i - 1;

                if ("\n" === $s[$i]) {
                    if ("\r" === $s[$k]) {
                        $i = $k;
                    }
                } elseif (0xCC <= \ord($s[$i])) {
                    // Only a char from U+0300 can extend the last trimmed one, e.g. a combining accent after a space
                    while (0x80 <= \ord($s[$k]) && 0xC0 > \ord($s[$k])) {
                        --$k;
                    }

                    if (preg_match('/\X/u', $s, $m, 0, $k) && $k + \strlen($m[0]) > $i) {
                        $i = $k;
                    }
                }
            }

            if ($i < $j && $j < \strlen($s)) {
                $k = $j - 1;

                if ("\r" === $s[$k]) {
                    if ("\n" === $s[$j]) {
                        ++$j;
                    }
                } elseif (0x80 <= \ord($s[$k])) {
                    while (0xC0 > \ord($s[$k])) {
                        --$k;
                    }

                    // Only a char from U+0600 can be extended by the first trimmed one, e.g. a prepended concatenation mark before a space
                    if (0xD8 <= \ord($s[$k]) && preg_match('/\X/u', $s, $m, 0, $k) && $k + \strlen($m[0]) > $j) {
                        $j = $k + \strlen($m[0]);
                    }
                }
            }
        } else {
            if ($start) {
                while ($i < $j && preg_match('/\X/u', $s, $m, 0, $i) && (isset($graphemes[$m[0]]) || ($ranges && preg_match($ranges, $m[0])))) {
                    $i += \strlen($m[0]);
                }
            }

            if ($end && $i < $j) {
                // Segment the end of the string, starting at or before the last char that is not in $chars
                preg_match("{\G(?:.*[^$rx])?}su", $s, $m, 0, $i);
                preg_match_all('/\X/u', $s, $tail, 0, self::findSegmentationStart($s, max($i, $i + \strlen($m[0]) - 1), $i));

                for ($k = \count($tail[0]) - 1; 0 <= $k && (isset($graphemes[$tail[0][$k]]) || ($ranges && preg_match($ranges, $tail[0][$k]))); --$k) {
                    $j -= \strlen($tail[0][$k]);
                }
            }
        }

        if (0 !== $i || \strlen($s) !== $j) {
            $str->string = substr($s, $i, $j - $i);
        }

        return $str;
    }

    /**
     * @return array{string, array<string, true>|null, string|null}
     */
    private static function parseTrimChars(string $chars): array
    {
        if (false === $codePoints = preg_split('//u', $chars, -1, \PREG_SPLIT_NO_EMPTY)) {
            throw new InvalidArgumentException('Invalid UTF-8 chars.');
        }

        // Like PHP's trim(), read "x..y" as the range of code points from x to y
        $pieces = [''];
        $ranges = '';
        $slow = false;

        for ($n = \count($codePoints), $i = 0; $i < $n; ++$i) {
            if ($i + 3 < $n && '.' === $codePoints[$i + 1] && '.' === $codePoints[$i + 2] && ($from = mb_ord($codePoints[$i], 'UTF-8')) <= ($to = mb_ord($codePoints[$i + 3], 'UTF-8'))) {
                $ranges .= \sprintf('\x{%X}-\x{%X}', $from, $to);
                $slow = $slow || 0x80 <= $to;
                $pieces[] = '';
                $i += 3;
            } elseif ('.' !== $codePoints[$i] || '.' !== ($codePoints[$i + 1] ?? '')) {
                $pieces[\count($pieces) - 1] .= $codePoints[$i];
            }
        }

        $pieces = array_map('normalizer_normalize', $pieces);
        $codePoints = array_unique(preg_split('//u', implode('', $pieces), -1, \PREG_SPLIT_NO_EMPTY));
        $rx = preg_quote(implode('', $codePoints)).$ranges;

        // Each probe makes 21 grapheme clusters unless the char joins a neighbor as a combining mark, a prepended concatenation mark,
        // a regional indicator, a Hangul jamo or syllable, or the end of an emoji ZWJ sequence or of an Indic conjunct
        $probe = '';
        foreach ($codePoints as $c) {
            $probe .= "a{$c}a{$c}\u{1F1E6}\0{$c}\u{1100}\0{$c}\u{1161}\0{$c}\u{11A8}\0\u{1F600}\u{200D}{$c}\0\u{915}\u{94D}{$c}\0";
        }

        if (!$slow && 21 * \count($codePoints) === preg_match_all('/\X/u', $probe)) {
            return [$rx, null, null];
        }

        $graphemes = [];

        foreach ($pieces as $piece) {
            preg_match_all('/\X/u', $piece, $m);

            foreach ($m[0] as $grapheme) {
                if ("\r\n" === $grapheme) {
                    $graphemes["\r"] = $graphemes["\n"] = true;
                } else {
                    $graphemes[$grapheme] = true;
                }
            }
        }

        // A range matches the grapheme clusters made of a single code point
        $ranges = '' !== $ranges ? "{^[$ranges]$}uD" : null;

        if ((isset($graphemes["\r"]) || ($ranges && preg_match($ranges, "\r"))) && (isset($graphemes["\n"]) || ($ranges && preg_match($ranges, "\n")))) {
            $graphemes["\r\n"] = true;
        }

        return [$rx, $graphemes, $ranges];
    }
}
