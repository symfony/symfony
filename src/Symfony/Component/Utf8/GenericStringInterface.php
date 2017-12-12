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

use Symfony\Component\Utf8\Exception\ExceptionInterface;

/**
 * Represents a generic string implementation.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 * @author Hugo Hamon <hugohamon@neuf.fr>
 *
 * @throws ExceptionInterface
 *
 * @final
 */
interface GenericStringInterface extends \IteratorAggregate
{
    public function __toString(): string;

    public function indexOf(string $needle, int $offset = 0): ?int;

    public function indexOfIgnoreCase(string $needle, int $offset = 0): ?int;

    public function lastIndexOf(string $needle, int $offset = 0): ?int;

    public function lastIndexOfIgnoreCase(string $needle, int $offset = 0): ?int;

    /**
     * @return static|null
     */
    public function substringOf(string $needle, bool $beforeNeedle = false);

    /**
     * @return static|null
     */
    public function substringOfIgnoreCase(string $needle, bool $beforeNeedle = false);

    /**
     * @return static|null
     */
    public function lastSubstringOf(string $needle, bool $beforeNeedle = false);

    /**
     * @return static|null
     */
    public function lastSubstringOfIgnoreCase(string $needle, bool $beforeNeedle = false);

    /**
     * @return static
     */
    public function reverse();

    public function length(): int;

    public function width(bool $ignoreAnsiDecoration = true): int;

    public function isEmpty(): bool;

    /**
     * Splits the string into smaller chunks separated by a delimiter.
     *
     * @return static[]
     */
    public function explode(string $delimiter, int $limit = null): array;

    /**
     * @return static
     */
    public function toLowerCase();

    /**
     * @return static
     */
    public function toUpperCase();

    /**
     * @return static
     */
    public function toUpperCaseFirst($allWords = false);

    /**
     * @return static
     */
    public function toFoldedCase(bool $full = true);

    /**
     * @return static
     */
    public function substr(int $start = 0, int $length = null);

    /**
     * @return static
     */
    public function trim(string $charsList = null);

    /**
     * @return static
     */
    public function trimLeft(string $charsList = null);

    /**
     * @return static
     */
    public function trimRight(string $charsList = null);

    /**
     * @return static
     */
    public function append(string $suffix);

    /**
     * @return static
     */
    public function prepend(string $prefix);

    /**
     * @return static
     */
    public function replace(string $from, string $to, int &$count = null);

    /**
     * @param string[] $from
     * @param string[] $to
     *
     * @return static
     */
    public function replaceAll(array $from, array $to, int &$count = null);

    /**
     * @return static
     */
    public function replaceIgnoreCase(string $from, string $to, int &$count = null);

    /**
     * @param string[] $from
     * @param string[] $to
     *
     * @return static
     */
    public function replaceAllIgnoreCase(array $from, array $to, int &$count = null);

    public function toBytes(): Bytes;

    public function toCodePoints(): CodePoints;

    public function toGraphemes(): Graphemes;
}
