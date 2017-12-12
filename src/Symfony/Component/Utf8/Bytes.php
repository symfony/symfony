<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Utf8;

use Symfony\Component\Utf8\Exception\InvalidArgumentException;

final class Bytes implements GenericStringInterface, \Countable
{
    private $string = '';

    public static function fromString(string $string): self
    {
        $bytes = new self();
        $bytes->string = $string;

        return $bytes;
    }

    public static function fromCharCode(int ...$codes): self
    {
        $bytes = new self();
        $bytes->string = implode('', array_map('chr', $codes));

        return $bytes;
    }

    /**
     * {@inheritdoc}
     */
    public function __toString(): string
    {
        return $this->string;
    }

    /**
     * {@inheritdoc}
     */
    public function count(): int
    {
        return strlen($this->string);
    }

    /**
     * {@inheritdoc}
     */
    public function length(): int
    {
        return strlen($this->string);
    }

    /**
     * {@inheritdoc}
     */
    public function isEmpty(): bool
    {
        return '' === $this->string;
    }

    /**
     * {@inheritdoc}
     */
    public function explode(string $delimiter, int $limit = null): array
    {
        if ('' === $delimiter) {
            throw new InvalidArgumentException('Passing an empty delimiter is not supported by this method. Use getIterator() method instead.');
        }

        $chunks = array();
        foreach (explode($delimiter, $this->string, $limit ?: PHP_INT_MAX) as $i => $string) {
            $chunks[$i] = $chunk = clone $this;
            $chunk->string = $string;
        }

        return $chunks;
    }

    /**
     * {@inheritdoc}
     */
    public function toLowerCase(): self
    {
        $current = setlocale(LC_CTYPE, '0');
        setlocale(LC_CTYPE, 'C');

        $result = clone $this;

        try {
            $result->string = strtolower($this->string);
        } finally {
            setlocale(LC_CTYPE, $current);
        }

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function toUpperCase(): self
    {
        $current = setlocale(LC_CTYPE, '0');
        setlocale(LC_CTYPE, 'C');

        $result = clone $this;

        try {
            $result->string = strtoupper($this->string);
        } finally {
            setlocale(LC_CTYPE, $current);
        }

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function toUpperCaseFirst($allWords = false): self
    {
        $current = setlocale(LC_CTYPE, '0');
        setlocale(LC_CTYPE, 'C');

        $result = clone $this;

        try {
            $result->string = $allWords ? ucwords($this->string) : ucfirst($this->string);
        } finally {
            setlocale(LC_CTYPE, $current);
        }

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function toFoldedCase(bool $full = true): self
    {
        return $this->toLowerCase();
    }

    /**
     * {@inheritdoc}
     */
    public function substr(int $start = 0, int $length = null): self
    {
        $result = clone $this;
        $result->string = substr($this->string, $start, $length);

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function trim(string $charsList = null): self
    {
        $result = clone $this;
        $result->string = trim($this->string, $charsList ?: " \t\n\r\0\x0B");

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function trimLeft(string $charsList = null): self
    {
        $result = clone $this;
        $result->string = ltrim($this->string, $charsList ?: " \t\n\r\0\x0B");

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function trimRight(string $charsList = null): self
    {
        $result = clone $this;
        $result->string = rtrim($this->string, $charsList ?: " \t\n\r\0\x0B");

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function getIterator(int $maxChunkLength = 1): \Traversable
    {
        if (1 > $maxChunkLength) {
            throw new InvalidArgumentException('The maximum length of each segment must be greater than zero.');
        }

        if ('' === $this->string) {
            return null;
        }

        foreach (str_split($this->string, $maxChunkLength) as $char) {
            $clone = clone $this;
            $clone->string = $char;
            yield $clone;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function append(string $suffix): self
    {
        $result = clone $this;
        $result->string .= $suffix;

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function prepend(string $prefix): self
    {
        $result = clone $this;
        $result->string = $prefix.$result->string;

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function indexOf(string $needle, int $offset = 0): ?int
    {
        if ('' === $needle) {
            return null;
        }

        if (0 <= $offset || PHP_VERSION_ID >= 70100) {
            $result = strpos($this->string, $needle, $offset);

            return false === $result ? null : $result;
        }

        // Workaround to support negative offsets with strpos() in PHP < 7.1
        $start = $offset + strlen($this->string);
        $offset = strpos(substr($this->string, $start), $needle);

        return false === $offset ? null : $start + $offset;
    }

    /**
     * {@inheritdoc}
     */
    public function indexOfIgnoreCase(string $needle, int $offset = 0): ?int
    {
        if ('' === $needle) {
            return null;
        }

        $current = setlocale(LC_CTYPE, '0');
        setlocale(LC_CTYPE, 'C');

        try {
            if (0 <= $offset || PHP_VERSION_ID >= 70100) {
                $result = stripos($this->string, $needle, $offset);
                $result = false === $result ? null : $result;
            } else {
                // Workaround to support negative offsets with stripos() in PHP < 7.1
                $start = $offset + strlen($this->string);
                $offset = stripos(substr($this->string, $start), $needle);
                $result = false === $offset ? null : $start + $offset;
            }
        } finally {
            setlocale(LC_CTYPE, $current);
        }

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function lastIndexOf(string $needle, int $offset = 0): ?int
    {
        if ('' === $needle) {
            return null;
        }

        $result = strrpos($this->string, $needle, $offset);

        return false === $result ? null : $result;
    }

    /**
     * {@inheritdoc}
     */
    public function lastIndexOfIgnoreCase(string $needle, int $offset = 0): ?int
    {
        if ('' === $needle) {
            return null;
        }

        $current = setlocale(LC_CTYPE, '0');
        setlocale(LC_CTYPE, 'C');

        try {
            $result = strripos($this->string, $needle, $offset);
        } finally {
            setlocale(LC_CTYPE, $current);
        }

        return false === $result ? null : $result;
    }

    /**
     * {@inheritdoc}
     */
    public function substringOf(string $needle, bool $beforeNeedle = false)
    {
        if ('' === $needle) {
            return null;
        }

        if (false === $part = strstr($this->string, $needle, $beforeNeedle)) {
            return null;
        }

        $result = clone $this;
        $result->string = $part;

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function substringOfIgnoreCase(string $needle, bool $beforeNeedle = false)
    {
        if ('' === $needle) {
            return null;
        }

        $result = null;
        $current = setlocale(LC_CTYPE, '0');
        setlocale(LC_CTYPE, 'C');

        try {
            if (false !== $part = stristr($this->string, $needle, $beforeNeedle)) {
                $result = clone $this;
                $result->string = $part;
            }
        } finally {
            setlocale(LC_CTYPE, $current);
        }

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function lastSubstringOf(string $needle, bool $beforeNeedle = false)
    {
        if ('' === $needle) {
            return null;
        }

        if (!$beforeNeedle) {

            if (false === $part = strrchr($this->string, $needle)) {
                return null;
            }

            $result = clone $this;
            $result->string = $part;

            return $result;
        }

        if (false === $offset = strrpos($this->string, $needle)) {
            return null;
        }

        $result = clone $this;
        $result->string = substr($this->string, 0, $offset);

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function lastSubstringOfIgnoreCase(string $needle, bool $beforeNeedle = false)
    {
        if ('' === $needle) {
            return null;
        }

        if (false === $offset = strripos($this->string, $needle)) {
            return null;
        }

        $result = null;
        $current = setlocale(LC_CTYPE, '0');
        setlocale(LC_CTYPE, 'C');

        try {
            $part = $beforeNeedle ? substr($this->string, 0, $offset) : substr($this->string, $offset);
            if (false !== $part) {
                $result = clone $this;
                $result->string = $part;
            }
        } finally {
            setlocale(LC_CTYPE, $current);
        }

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function reverse(): self
    {
        $result = clone $this;
        $result->string = strrev($this->string);

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function width(bool $ignoreAnsiDecoration = true): int
    {
        $s = $this->string;

        if (false !== strpos($s, "\r")) {
            $s = str_replace("\r\n", "\n", $s);
            $s = strtr($s, "\r", "\n");
        }

        $width = 0;
        foreach (explode("\n", $s) as $s) {
            if ($ignoreAnsiDecoration) {
                $s = preg_replace('/\x1B\[[\d;]*m/', '', $s);
            }

            if ($width < $c = strlen($s)) {
                $width = $c;
            }
        }

        return $width;
    }

    /**
     * {@inheritdoc}
     */
    public function replace(string $from, string $to, int &$count = null): self
    {
        $result = clone $this;
        $result->string = str_replace($from, $to, $this->string, $count);

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function replaceAll(array $from, array $to, int &$count = null): self
    {
        if (count($from) !== count($to)) {
            throw new InvalidArgumentException('The number of search patterns does not match the number of pattern replacements.');
        }

        foreach ($from as $k => $pattern) {
            if (!is_string($pattern)) {
                throw new InvalidArgumentException(sprintf('Search pattern at key %s must be a valid string.', $k));
            }
        }

        foreach ($to as $k => $replacement) {
            if (!is_string($replacement)) {
                throw new InvalidArgumentException(sprintf('Pattern replacement at key %s must be a valid string.', $k));
            }
        }

        $result = clone $this;
        $result->string = str_replace($from, $to, $this->string, $count);

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function replaceIgnoreCase(string $from, string $to, int &$count = null): self
    {
        $current = setlocale(LC_CTYPE, '0');
        setlocale(LC_CTYPE, 'C');

        $result = clone $this;

        try {
            $result->string = str_ireplace($from, $to, $this->string, $count);
        } finally {
            setlocale(LC_CTYPE, $current);
        }

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function replaceAllIgnoreCase(array $from, array $to, int &$count = null): self
    {
        if (count($from) !== count($to)) {
            throw new InvalidArgumentException('The number of search patterns does not match the number of pattern replacements.');
        }

        foreach ($from as $k => $pattern) {
            if (!is_string($pattern)) {
                throw new InvalidArgumentException(sprintf('Search pattern at key %s must be a valid string.', $k));
            }
        }

        foreach ($to as $k => $replacement) {
            if (!is_string($replacement)) {
                throw new InvalidArgumentException(sprintf('Pattern replacement at key %s must be a valid string.', $k));
            }
        }

        $current = setlocale(LC_CTYPE, '0');
        setlocale(LC_CTYPE, 'C');

        $result = clone $this;

        try {
            $result->string = str_ireplace($from, $to, $this->string, $count);
        } finally {
            setlocale(LC_CTYPE, $current);
        }

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function toBytes(): self
    {
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function toCodePoints(): CodePoints
    {
        return CodePoints::fromString($this->string);
    }

    /**
     * {@inheritdoc}
     */
    public function toGraphemes(): Graphemes
    {
        return Graphemes::fromString($this->string);
    }
}
