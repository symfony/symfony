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

final class Graphemes implements GenericStringInterface, \Countable
{
    use Utf8Trait;

    private static $hasIntl;

    /**
     * {@inheritdoc}
     */
    public function count(): int
    {
        return grapheme_strlen($this->string);
    }

    /**
     * {@inheritdoc}
     */
    public function length(): int
    {
        return grapheme_strlen($this->string);
    }

    /**
     * {@inheritdoc}
     */
    public function substr(int $start = 0, int $length = null): self
    {
        // Workaround to support negative offsets with grapheme_substr() in HHVM
        if (0 > $start && defined('HHVM_VERSION')) {
            $start += grapheme_strlen($this->string);
        }

        $result = clone $this;
        $result->string = grapheme_substr($this->string, $start, $length);

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

        if (65535 < $maxChunkLength) {
            throw new InvalidArgumentException('Maximum chunk length must not exceed 65535.');
        }

        if (null === self::$hasIntl) {
            self::$hasIntl = extension_loaded('intl');
        }

        if (self::$hasIntl) {
            $length = strlen($this->string);
            $i = 0;

            while ($i < $length) {
                $clone = clone $this;
                $clone->string = grapheme_extract($this->string, $maxChunkLength, GRAPHEME_EXTR_COUNT, $i, $i);
                yield $clone;
            }

            return null;
        }

        // Intl extension is not available.
        foreach (preg_split('/(\X{'.$maxChunkLength.'})/u', $this->string, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY) as $char) {
            $clone = clone $this;
            $clone->string = $char;
            yield $clone;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function indexOf(string $needle, int $offset = 0): ?int
    {
        if ('' === $needle) {
            return null;
        }

        $result = grapheme_strpos($this->string, $needle, $offset);

        return false === $result ? null : $result;
    }

    /**
     * {@inheritdoc}
     */
    public function indexOfIgnoreCase(string $needle, int $offset = 0): ?int
    {
        if ('' === $needle) {
            return null;
        }

        $result = grapheme_stripos($this->string, $needle, $offset);

        return false === $result ? null : $result;
    }

    /**
     * {@inheritdoc}
     */
    public function lastIndexOf(string $needle, int $offset = 0): ?int
    {
        if ('' === $needle) {
            return null;
        }

        // Workaround for bug 74264
        // @see https://bugs.php.net/bug.php?id=74264
        if (0 > $offset) {
            if (false === $pos = strrpos($this->string, $needle, $offset)) {
                return null;
            }

            return grapheme_strlen(substr($this->string, 0, $pos));
        }

        $result = grapheme_strrpos($this->string, $needle, $offset);

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

        // Workaround for bug 74264
        // @see https://bugs.php.net/bug.php?id=74264
        if (0 > $offset) {
            if (false === $pos = mb_strripos($this->string, $needle, $offset)) {
                return null;
            }

            return grapheme_strlen(mb_substr($this->string, 0, $pos));
        }

        $result = grapheme_strripos($this->string, $needle, $offset);

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

        if (false === $part = grapheme_strstr($this->string, $needle, $beforeNeedle)) {
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

        if (false === $part = grapheme_stristr($this->string, $needle, $beforeNeedle)) {
            return null;
        }

        $result = clone $this;
        $result->string = $part;

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

            $c = substr_count($s, "\xAD") - substr_count($s, "\x08");
            $s = preg_replace('/[\x00\x05\x07\p{Mn}\p{Me}\p{Cf}\x{1160}-\x{11FF}\x{200B}]+/u', '', $s);
            preg_replace('/[\x{1100}-\x{115F}\x{2329}\x{232A}\x{2E80}-\x{303E}\x{3040}-\x{A4CF}\x{AC00}-\x{D7A3}\x{F900}-\x{FAFF}\x{FE10}-\x{FE19}\x{FE30}-\x{FE6F}\x{FF00}-\x{FF60}\x{FFE0}-\x{FFE6}\x{20000}-\x{2FFFD}\x{30000}-\x{3FFFD}]/u', '', $s, -1, $wide);

            if ($width < $c = grapheme_strlen($s) + $wide + $c) {
                $width = $c;
            }
        }

        return $width;
    }

    /**
     * {@inheritdoc}
     */
    public function toBytes(): Bytes
    {
        return Bytes::fromString($this->string);
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
    public function toGraphemes(): self
    {
        return $this;
    }
}
