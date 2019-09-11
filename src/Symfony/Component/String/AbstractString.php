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
use Symfony\Component\String\Exception\RuntimeException;

/**
 * Represents a string of abstract characters.
 *
 * Unicode defines 3 types of "characters" (bytes, code points and grapheme clusters).
 * This class is the abstract type to use as a type-hint when the logic you want to
 * implement doesn't care about the exact variant it deals with.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 * @author Hugo Hamon <hugohamon@neuf.fr>
 *
 * @throws ExceptionInterface
 *
 * @experimental in 5.0
 */
abstract class AbstractString implements \JsonSerializable
{
    public const PREG_PATTERN_ORDER = \PREG_PATTERN_ORDER;
    public const PREG_SET_ORDER = \PREG_SET_ORDER;
    public const PREG_OFFSET_CAPTURE = \PREG_OFFSET_CAPTURE;
    public const PREG_UNMATCHED_AS_NULL = \PREG_UNMATCHED_AS_NULL;

    public const PREG_SPLIT = 0;
    public const PREG_SPLIT_NO_EMPTY = \PREG_SPLIT_NO_EMPTY;
    public const PREG_SPLIT_DELIM_CAPTURE = \PREG_SPLIT_DELIM_CAPTURE;
    public const PREG_SPLIT_OFFSET_CAPTURE = \PREG_SPLIT_OFFSET_CAPTURE;

    protected $string = '';
    protected $ignoreCase = false;

    abstract public function __construct(string $string = '');

    /**
     * Unwraps instances of AbstractString back to strings.
     *
     * @return string[]|array
     */
    public static function unwrap(array $values): array
    {
        foreach ($values as $k => $v) {
            if ($v instanceof self) {
                $values[$k] = $v->__toString();
            } elseif (\is_array($v) && $values[$k] !== $v = static::unwrap($v)) {
                $values[$k] = $v;
            }
        }

        return $values;
    }

    /**
     * Wraps (and normalizes) strings in instances of AbstractString.
     *
     * @return static[]|array
     */
    public static function wrap(array $values): array
    {
        $i = 0;
        $keys = null;

        foreach ($values as $k => $v) {
            ++$i;

            if (\is_string($k) && '' !== $k && $k !== $j = (string) new static($k)) {
                $keys = $keys ?? array_keys($values);
                array_splice($keys, $i, 1, [$j]);
            }

            if (\is_string($v)) {
                $values[$k] = new static($v);
            } elseif (\is_array($v) && $values[$k] !== $v = static::wrap($v)) {
                $values[$k] = $v;
            }
        }

        return null !== $keys ? array_combine($keys, $values) : $values;
    }

    /**
     * @param string|string[] $needle
     *
     * @return static
     */
    public function after($needle, bool $includeNeedle = false, int $offset = 0): self
    {
        $str = clone $this;
        $str->string = '';
        $i = \PHP_INT_MAX;

        foreach ((array) $needle as $n) {
            $n = (string) $n;
            $j = $this->indexOf($n, $offset);

            if (null !== $j && $j < $i) {
                $i = $j;
                $str->string = $n;
            }
        }

        if (\PHP_INT_MAX === $i) {
            return $str;
        }

        if (!$includeNeedle) {
            $i += $str->length();
        }

        return $this->slice($i);
    }

    /**
     * @param string|string[] $needle
     *
     * @return static
     */
    public function afterLast($needle, bool $includeNeedle = false, int $offset = 0): self
    {
        $str = clone $this;
        $str->string = '';
        $i = null;

        foreach ((array) $needle as $n) {
            $n = (string) $n;
            $j = $this->indexOfLast($n, $offset);

            if (null !== $j && $j > $i) {
                $i = $offset = $j;
                $str->string = $n;
            }
        }

        if (null === $i) {
            return $str;
        }

        if (!$includeNeedle) {
            $i += $str->length();
        }

        return $this->slice($i);
    }

    /**
     * @return static
     */
    abstract public function append(string ...$suffix): self;

    /**
     * @param string|string[] $needle
     *
     * @return static
     */
    public function before($needle, bool $includeNeedle = false, int $offset = 0): self
    {
        $str = clone $this;
        $str->string = '';
        $i = \PHP_INT_MAX;

        foreach ((array) $needle as $n) {
            $n = (string) $n;
            $j = $this->indexOf($n, $offset);

            if (null !== $j && $j < $i) {
                $i = $j;
                $str->string = $n;
            }
        }

        if (\PHP_INT_MAX === $i) {
            return $str;
        }

        if ($includeNeedle) {
            $i += $str->length();
        }

        return $this->slice(0, $i);
    }

    /**
     * @param string|string[] $needle
     *
     * @return static
     */
    public function beforeLast($needle, bool $includeNeedle = false, int $offset = 0): self
    {
        $str = clone $this;
        $str->string = '';
        $i = null;

        foreach ((array) $needle as $n) {
            $n = (string) $n;
            $j = $this->indexOfLast($n, $offset);

            if (null !== $j && $j > $i) {
                $i = $offset = $j;
                $str->string = $n;
            }
        }

        if (null === $i) {
            return $str;
        }

        if ($includeNeedle) {
            $i += $str->length();
        }

        return $this->slice(0, $i);
    }

    /**
     * @return static
     */
    abstract public function camel(): self;

    /**
     * @return static[]
     */
    abstract public function chunk(int $length = 1): array;

    /**
     * @return static
     */
    public function collapseWhitespace(): self
    {
        $str = clone $this;
        $str->string = trim(preg_replace('/(?:\s{2,}+|[^\S ])/', ' ', $str->string));

        return $str;
    }

    /**
     * @param string|string[] $suffix
     */
    public function endsWith($suffix): bool
    {
        if (!\is_array($suffix) && !$suffix instanceof \Traversable) {
            throw new \TypeError(sprintf('Method "%s()" must be overridden by class "%s" to deal with non-iterable values.', __FUNCTION__, \get_class($this)));
        }

        foreach ($suffix as $s) {
            if ($this->endsWith((string) $s)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return static
     */
    public function ensureEnd(string $suffix): self
    {
        if (!$this->endsWith($suffix)) {
            return $this->append($suffix);
        }

        $suffix = preg_quote($suffix);
        $regex = '{('.$suffix.')(?:'.$suffix.')++$}D';

        return $this->replaceMatches($regex.($this->ignoreCase ? 'i' : ''), '$1');
    }

    /**
     * @return static
     */
    public function ensureStart(string $prefix): self
    {
        $prefix = new static($prefix);

        if (!$this->startsWith($prefix)) {
            return $this->prepend($prefix);
        }

        $str = clone $this;
        $i = $prefixLen = $prefix->length();

        while ($this->indexOf($prefix, $i) === $i) {
            $str = $str->slice($prefixLen);
            $i += $prefixLen;
        }

        return $str;
    }

    /**
     * @param string|string[] $string
     */
    public function equalsTo($string): bool
    {
        if (!\is_array($string) && !$string instanceof \Traversable) {
            throw new \TypeError(sprintf('Method "%s()" must be overridden by class "%s" to deal with non-iterable values.', __FUNCTION__, \get_class($this)));
        }

        foreach ($string as $s) {
            if ($this->equalsTo((string) $s)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return static
     */
    abstract public function folded(): self;

    /**
     * @return static
     */
    public function ignoreCase(): self
    {
        $str = clone $this;
        $str->ignoreCase = true;

        return $str;
    }

    /**
     * @param string|string[] $needle
     */
    public function indexOf($needle, int $offset = 0): ?int
    {
        if (!\is_array($needle) && !$needle instanceof \Traversable) {
            throw new \TypeError(sprintf('Method "%s()" must be overridden by class "%s" to deal with non-iterable values.', __FUNCTION__, \get_class($this)));
        }

        $i = \PHP_INT_MAX;

        foreach ($needle as $n) {
            $j = $this->indexOf((string) $n, $offset);

            if (null !== $j && $j < $i) {
                $i = $j;
            }
        }

        return \PHP_INT_MAX === $i ? null : $i;
    }

    /**
     * @param string|string[] $needle
     */
    public function indexOfLast($needle, int $offset = 0): ?int
    {
        if (!\is_array($needle) && !$needle instanceof \Traversable) {
            throw new \TypeError(sprintf('Method "%s()" must be overridden by class "%s" to deal with non-iterable values.', __FUNCTION__, \get_class($this)));
        }

        $i = null;

        foreach ($needle as $n) {
            $j = $this->indexOfLast((string) $n, $offset);

            if (null !== $j && $j >= $i) {
                $i = $offset = $j;
            }
        }

        return $i;
    }

    public function isEmpty(): bool
    {
        return '' === $this->string;
    }

    /**
     * @return static
     */
    abstract public function join(array $strings): self;

    public function jsonSerialize(): string
    {
        return $this->string;
    }

    abstract public function length(): int;

    /**
     * @return static
     */
    abstract public function lower(): self;

    /**
     * Matches the string using a regular expression.
     *
     * Pass PREG_PATTERN_ORDER or PREG_SET_ORDER as $flags to get all occurrences matching the pattern.
     *
     * @return array All matches in a multi-dimensional array ordered according to flags
     */
    abstract public function match(string $pattern, int $flags = 0, int $offset = 0): array;

    /**
     * @return static
     */
    abstract public function padBoth(int $length, string $padStr = ' '): self;

    /**
     * @return static
     */
    abstract public function padEnd(int $length, string $padStr = ' '): self;

    /**
     * @return static
     */
    abstract public function padStart(int $length, string $padStr = ' '): self;

    /**
     * @return static
     */
    abstract public function prepend(string ...$prefix): self;

    /**
     * @return static
     */
    public function repeat(int $multiplier): self
    {
        if (0 > $multiplier) {
            throw new InvalidArgumentException(sprintf('Multiplier must be positive, %d given.', $multiplier));
        }

        $str = clone $this;
        $str->string = str_repeat($str->string, $multiplier);

        return $str;
    }

    /**
     * @return static
     */
    abstract public function replace(string $from, string $to): self;

    /**
     * @param string|callable $to
     *
     * @return static
     */
    abstract public function replaceMatches(string $fromPattern, $to): self;

    /**
     * @return static
     */
    abstract public function slice(int $start = 0, int $length = null): self;

    /**
     * @return static
     */
    abstract public function snake(): self;

    /**
     * @return static
     */
    abstract public function splice(string $replacement, int $start = 0, int $length = null): self;

    /**
     * @return static[]
     */
    public function split(string $delimiter, int $limit = null, int $flags = null): array
    {
        if (null === $flags) {
            throw new \TypeError('Split behavior when $flags is null must be implemented by child classes.');
        }

        if ($this->ignoreCase) {
            $delimiter .= 'i';
        }

        set_error_handler(static function ($t, $m) { throw new InvalidArgumentException($m); });

        try {
            if (false === $chunks = preg_split($delimiter, $this->string, $limit, $flags)) {
                $lastError = preg_last_error();

                foreach (get_defined_constants(true)['pcre'] as $k => $v) {
                    if ($lastError === $v && '_ERROR' === substr($k, -6)) {
                        throw new RuntimeException('Splitting failed with '.$k.'.');
                    }
                }

                throw new RuntimeException('Splitting failed with unknown error code.');
            }
        } finally {
            restore_error_handler();
        }

        $str = clone $this;

        if (self::PREG_SPLIT_OFFSET_CAPTURE & $flags) {
            foreach ($chunks as &$chunk) {
                $str->string = $chunk[0];
                $chunk[0] = clone $str;
            }
        } else {
            foreach ($chunks as &$chunk) {
                $str->string = $chunk;
                $chunk = clone $str;
            }
        }

        return $chunks;
    }

    /**
     * @param string|string[] $prefix
     */
    public function startsWith($prefix): bool
    {
        if (!\is_array($prefix) && !$prefix instanceof \Traversable) {
            throw new \TypeError(sprintf('Method "%s()" must be overridden by class "%s" to deal with non-iterable values.', __FUNCTION__, \get_class($this)));
        }

        foreach ($prefix as $prefix) {
            if ($this->startsWith((string) $prefix)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return static
     */
    abstract public function title(bool $allWords = false): self;

    public function toBinary(string $toEncoding = null): BinaryString
    {
        $b = new BinaryString();

        $toEncoding = \in_array($toEncoding, ['utf8', 'utf-8', 'UTF8'], true) ? 'UTF-8' : $toEncoding;

        if (null === $toEncoding || $toEncoding === $fromEncoding = $this instanceof AbstractUnicodeString || preg_match('//u', $b->string) ? 'UTF-8' : 'Windows-1252') {
            $b->string = $this->string;

            return $b;
        }

        set_error_handler(static function ($t, $m) { throw new InvalidArgumentException($m); });

        try {
            try {
                $b->string = mb_convert_encoding($this->string, $toEncoding, 'UTF-8');
            } catch (InvalidArgumentException $e) {
                if (!\function_exists('iconv')) {
                    throw $e;
                }

                $b->string = iconv('UTF-8', $toEncoding, $this->string);
            }
        } finally {
            restore_error_handler();
        }

        return $b;
    }

    public function toGrapheme(): GraphemeString
    {
        return new GraphemeString($this->string);
    }

    public function toUtf8(): Utf8String
    {
        return new Utf8String($this->string);
    }

    /**
     * @return static
     */
    abstract public function trim(string $chars = " \t\n\r\0\x0B\x0C\u{A0}\u{FEFF}"): self;

    /**
     * @return static
     */
    abstract public function trimEnd(string $chars = " \t\n\r\0\x0B\x0C\u{A0}\u{FEFF}"): self;

    /**
     * @return static
     */
    abstract public function trimStart(string $chars = " \t\n\r\0\x0B\x0C\u{A0}\u{FEFF}"): self;

    /**
     * @return static
     */
    public function truncate(int $length, string $ellipsis = ''): self
    {
        $stringLength = $this->length();

        if ($stringLength <= $length) {
            return clone $this;
        }

        $ellipsisLength = '' !== $ellipsis ? (new static($ellipsis))->length() : 0;

        if ($length < $ellipsisLength) {
            $ellipsisLength = 0;
        }

        $str = $this->slice(0, $length - $ellipsisLength);

        return $ellipsisLength ? $str->trimEnd()->append($ellipsis) : $str;
    }

    /**
     * @return static
     */
    abstract public function upper(): self;

    abstract public function width(bool $ignoreAnsiDecoration = true): int;

    /**
     * @return static
     */
    public function wordwrap(int $width = 75, string $break = "\n", bool $cut = false): self
    {
        $lines = '' !== $break ? $this->split($break) : [clone $this];
        $chars = [];
        $mask = '';

        if (1 === \count($lines) && '' === $lines[0]->string) {
            return $lines[0];
        }

        foreach ($lines as $i => $line) {
            if ($i) {
                $chars[] = $break;
                $mask .= '#';
            }

            foreach ($line->chunk() as $char) {
                $chars[] = $char->string;
                $mask .= ' ' === $char->string ? ' ' : '?';
            }
        }

        $string = '';
        $j = 0;
        $b = $i = -1;
        $mask = wordwrap($mask, $width, '#', $cut);

        while (false !== $b = strpos($mask, '#', $b + 1)) {
            for (++$i; $i < $b; ++$i) {
                $string .= $chars[$j];
                unset($chars[$j++]);
            }

            if ($break === $chars[$j] || ' ' === $chars[$j]) {
                unset($chars[$j++]);
            }

            $string .= $break;
        }

        $str = clone $this;
        $str->string = $string.implode('', $chars);

        return $str;
    }

    public function __clone()
    {
        $this->ignoreCase = false;
    }

    public function __toString(): string
    {
        return $this->string;
    }
}
