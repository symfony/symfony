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

final class CodePoints implements GenericStringInterface, \Countable
{
    use Utf8Trait;

    /**
     * {@inheritdoc}
     */
    public function count(): int
    {
        return mb_strlen($this->string, 'UTF-8');
    }

    /**
     * {@inheritdoc}
     */
    public function length(): int
    {
        return mb_strlen($this->string, 'UTF-8');
    }

    /**
     * {@inheritdoc}
     */
    public function substr(int $start = 0, int $length = null): self
    {
        $result = clone $this;
        $result->string = mb_substr($this->string, $start, $length, 'UTF-8');

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
            return;
        }

        if (65535 < $maxChunkLength) {
            throw new InvalidArgumentException('Maximum chunk length must not exceed 65535.');
        }

        foreach (preg_split('/(.{'.$maxChunkLength.'})/u', $this->string, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY) as $char) {
            $clone = clone $this;
            $clone->string = $char;
            yield $clone;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function indexOf(string $needle, int $offset = 0)
    {
        if ('' === $needle) {
            return;
        }

        if (0 <= $offset || PHP_VERSION_ID >= 70100) {
            $result = mb_strpos($this->string, $needle, $offset);

            return false === $result ? null : $result;
        }

        // Workaround to support negative offsets with mb_strpos() in PHP < 7.1
        $start = $offset + mb_strlen($this->string);
        $offset = mb_strpos(mb_substr($this->string, $start), $needle);

        return false === $offset ? null : $start + $offset;
    }

    /**
     * {@inheritdoc}
     */
    public function indexOfIgnoreCase(string $needle, int $offset = 0)
    {
        if ('' === $needle) {
            return;
        }

        if (0 <= $offset || PHP_VERSION_ID >= 70100) {
            $result = mb_stripos($this->string, $needle, $offset);

            return false === $result ? null : $result;
        }

        // Workaround to support negative offsets with mb_stripos() in PHP < 7.1
        $start = $offset + mb_strlen($this->string);
        $offset = mb_stripos(mb_substr($this->string, $start), $needle);

        return false === $offset ? null : $start + $offset;
    }

    /**
     * {@inheritdoc}
     */
    public function lastIndexOf(string $needle, int $offset = 0)
    {
        if ('' === $needle) {
            return;
        }

        $result = mb_strrpos($this->string, $needle, $offset);

        return false === $result ? null : $result;
    }

    /**
     * {@inheritdoc}
     */
    public function lastIndexOfIgnoreCase(string $needle, int $offset = 0)
    {
        if ('' === $needle) {
            return;
        }

        $result = mb_strripos($this->string, $needle, $offset);

        return false === $result ? null : $result;
    }

    /**
     * {@inheritdoc}
     */
    public function substringOf(string $needle, bool $beforeNeedle = false)
    {
        if ('' === $needle) {
            return;
        }

        if (false === $part = mb_strstr($this->string, $needle, $beforeNeedle, 'UTF-8')) {
            return;
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
            return;
        }

        if (false === $part = mb_stristr($this->string, $needle, $beforeNeedle, 'UTF-8')) {
            return;
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

            if ($width < $c = mb_strlen($s, 'UTF-8') + $wide + $c) {
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
    public function toCodePoints(): self
    {
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function toGraphemes(): Graphemes
    {
        return Graphemes::fromString($this->string);
    }
}
