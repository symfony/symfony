<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\CssSelector\Parser;

/**
 * CSS selector reader.
 *
 * This component is a port of the Python cssselect library,
 * which is copyright Ian Bicking, @see https://github.com/SimonSapin/cssselect.
 *
 * @author Jean-François Simon <jeanfrancois.simon@sensiolabs.com>
 *
 * @internal
 */
class Reader
{
    private static array $anchoredPatterns = [];

    private string $source;
    private int $length;
    private int $position = 0;

    public function __construct(string $source)
    {
        $this->source = $source;
        $this->length = \strlen($source);
    }

    public function isEOF(): bool
    {
        return $this->position >= $this->length;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function getRemainingLength(): int
    {
        return $this->length - $this->position;
    }

    public function getSubstring(int $length, int $offset = 0): string
    {
        return substr($this->source, $this->position + $offset, $length);
    }

    /**
     * @return int|false
     */
    public function getOffset(string $string): int|bool
    {
        $position = strpos($this->source, $string, $this->position);

        return false === $position ? false : $position - $this->position;
    }

    public function findPattern(string $pattern): array|false
    {
        // Match in place instead of copying the remaining source before every probe.
        // Combined with an offset, "^" still anchors at the start of the whole subject,
        // so a leading anchor is turned into "\\G", which anchors at the offset instead.
        $pattern = self::$anchoredPatterns[$pattern] ??= '^' === ($pattern[1] ?? '') ? $pattern[0].'\\G'.substr($pattern, 2) : $pattern;

        if (preg_match($pattern, $this->source, $matches, 0, $this->position)) {
            return $matches;
        }

        return false;
    }

    public function moveForward(int $length): void
    {
        $this->position += $length;
    }

    public function moveToEnd(): void
    {
        $this->position = $this->length;
    }
}
