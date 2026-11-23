<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\AssetMapper\Compiler\Parser;

/**
 * Finds the modules that some JavaScript code imports.
 *
 * The code is not parsed. The scanner only skips the comments, strings, template literals
 * and regular expressions, so that it never reads their content as import statements.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 *
 * @internal
 */
final class JavaScriptImportScanner
{
    private const SPACES = " \t\n\r\v\f";
    private const DELIMITERS = self::SPACES.'!"#%&\'()*+,-./:;<=>?@[\\]^`{|}~';
    private const PAREN_KEYWORDS = ['if' => true, 'for' => true, 'while' => true, 'with' => true];
    private const EXPRESSION_KEYWORDS = [
        'await' => true, 'case' => true, 'default' => true, 'delete' => true, 'do' => true, 'else' => true, 'in' => true,
        'instanceof' => true, 'new' => true, 'return' => true, 'throw' => true, 'typeof' => true, 'void' => true, 'yield' => true,
    ];
    private const UNICODE_SPACES = [
        "\u{A0}" => '  ', "\u{1680}" => '   ', "\u{2000}" => '   ', "\u{2001}" => '   ', "\u{2002}" => '   ', "\u{2003}" => '   ', "\u{2004}" => '   ',
        "\u{2005}" => '   ', "\u{2006}" => '   ', "\u{2007}" => '   ', "\u{2008}" => '   ', "\u{2009}" => '   ', "\u{200A}" => '   ', "\u{2028}" => '   ',
        "\u{2029}" => '   ', "\u{202F}" => '   ', "\u{205F}" => '   ', "\u{3000}" => '   ', "\u{FEFF}" => '   ',
    ];

    /**
     * @return list<array{int, string, bool}> The offset and the text of each module specifier, and whether the import is dynamic
     */
    public static function scan(string $code): array
    {
        $keywords = [];
        foreach (['import', 'export'] as $keyword) {
            for ($i = 0; false !== $i = strpos($code, $keyword, $i); $i += 6) {
                $keywords[] = $i;
            }
        }

        if (!$keywords) {
            return [];
        }

        sort($keywords);
        $source = $code;

        if (false !== strpbrk($code, "\xC2\xE1\xE2\xE3\xEF")) {
            // spaces of the same length preserve the offsets
            $code = strtr($code, self::UNICODE_SPACES);
        }

        $next = $keywords[$k = 0];
        $imports = $parens = $braces = [];
        $isKeywordParen = false;
        $division = -1;

        // $start is where the code that follows the last token begins, $prev is the offset of the last byte of that token
        $prev = -1;
        $i = $start = str_starts_with($code, '#!') ? strcspn($code, "\n\r") : 0;

        while (true) {
            while ($next < $i) {
                if (null === $next = $keywords[++$k] ?? null) {
                    return $imports;
                }
            }

            $p = $i + strcspn($code, '\'"`/(){}', $i, $next - $i);

            if ($p === $next) {
                $i = $p + 6;

                if (self::isWord($code, $p - 1) || self::isWord($code, $i) || 0 < $p && '#' === $code[$p - 1]) {
                    continue;
                }

                $isProperty = self::isMemberAccess($code, self::findLastToken($code, $p, $start, $prev));

                if (!$isProperty && $specifier = self::findSpecifier($code, $i, 'e' === $code[$p])) {
                    [$j, $end, $isDynamic] = $specifier;
                    $imports[] = [$j + 1, substr($source, $j + 1, $end - $j - 1), $isDynamic];
                }

                $prev = $i - 1;
                $start = $i;

                continue;
            }

            switch ($code[$p]) {
                case '(':
                    $parens[] = isset(self::PAREN_KEYWORDS[self::getWordEndingAt($code, self::findLastToken($code, $p, $start, $prev))]);
                    break;

                case ')':
                    $isKeywordParen = array_pop($parens) ?? false;
                    break;

                case '{':
                    $braces[] = false;
                    break;

                case '}':
                    if (!array_pop($braces)) {
                        break;
                    }
                    // no break: a substitution ends, its template literal continues

                case '`':
                    $p = self::skipTemplate($code, $p + 1, $isSubstitution);

                    if ($isSubstitution) {
                        $braces[] = true;
                    }
                    break;

                case '/':
                    $q = self::findLastToken($code, $p, $start, $prev);

                    if ($p < $i = self::skipSpaces($code, $p)) {
                        $prev = $q;
                        $start = $i;

                        continue 2;
                    }

                    if (self::isRegexAllowedAfter($code, $q, $division, $isKeywordParen) && 0 < $end = self::skipRegex($code, $p)) {
                        $p = $end;
                    } else {
                        $division = $p;
                    }
                    break;

                default:
                    $p = self::skipString($code, $p);
            }

            $prev = $p;
            $i = $start = $p + 1;
        }
    }

    /**
     * Looks for the module specifier of the statement that starts with an "import" or "export" keyword.
     *
     * @param int $i The offset that follows the keyword, moved after the part of the statement that is read
     *
     * @return array{int, int, bool}|null The offsets of the quotes of the specifier, and whether the import is dynamic
     */
    private static function findSpecifier(string $code, int &$i, bool $isExport): ?array
    {
        $c = $code[$j = self::skipSpaces($code, $i)] ?? '';

        if ($isExport) {
            if ('*' !== $c && '{' !== $c) {
                return null;
            }
        } elseif ('(' === $c) {
            $j = self::skipSpaces($code, $j + 1);

            if (0 < $end = self::findPlainStringEnd($code, $j)) {
                $c = $code[self::skipSpaces($code, $end + 1)] ?? '';

                if (')' === $c || ',' === $c) {
                    return [$j, $end, true];
                }
            }

            return null;
        }

        $last = '';

        while (true) {
            if ('"' === $c || "'" === $c) {
                if ('as' !== $last) {
                    return ('from' === $last || '' === $last) && 0 < ($end = self::findPlainStringEnd($code, $j)) ? [$j, $end, false] : null;
                }

                $j = self::skipString($code, $j, $isClosed);

                if (!$isClosed) {
                    return null;
                }
            } elseif ('{' === $c) {
                if (0 > $j = self::skipNamedBindings($code, $j)) {
                    return null;
                }
            } elseif ('*' !== $c && ',' !== $c) {
                if (!$n = strcspn($code, self::DELIMITERS, $j)) {
                    return null;
                }

                $c = substr($code, $j, $n);
                $j += $n - 1;
            }

            $last = $c;
            $i = $j + 1;
            $c = $code[$j = self::skipSpaces($code, $i)] ?? '';
        }
    }

    /**
     * Returns the offset of the last byte that is not a space in the code that precedes the given offset.
     */
    private static function findLastToken(string $code, int $offset, int $start, int $prev): int
    {
        while ($start <= --$offset) {
            if (!str_contains(self::SPACES, $code[$offset])) {
                return $offset;
            }
        }

        return $prev;
    }

    private static function isWord(string $code, int $offset): bool
    {
        return 0 <= $offset && isset($code[$offset]) && !str_contains(self::DELIMITERS, $code[$offset]);
    }

    private static function isMemberAccess(string $code, int $offset): bool
    {
        return 0 <= $offset && '.' === $code[$offset] && (!$offset || '.' !== $code[$offset - 1]);
    }

    /**
     * Returns the name or the keyword that ends at the given offset, unless it is the name of a property.
     */
    private static function getWordEndingAt(string $code, int $end): string
    {
        $i = $end;

        while (0 <= $i && !str_contains(self::DELIMITERS, $code[$i])) {
            --$i;
        }

        return $i === $end || self::isMemberAccess($code, $i) ? '' : substr($code, $i + 1, $end - $i);
    }

    /**
     * Tells whether a slash that follows the token ending at the given offset can start a regular expression.
     */
    private static function isRegexAllowedAfter(string $code, int $offset, int $division, bool $isKeywordParen): bool
    {
        if (0 > $offset) {
            return true;
        }

        return match ($c = $code[$offset]) {
            ')' => $isKeywordParen,
            ']', '"', "'", '`' => false,
            '/' => $offset === $division,
            '.' => 0 < $offset && '.' === $code[$offset - 1],
            '+', '-' => !$offset || $c !== $code[$offset - 1],
            default => str_contains(self::DELIMITERS, $c) || isset(self::EXPRESSION_KEYWORDS[self::getWordEndingAt($code, $offset)]),
        };
    }

    /**
     * Skips spaces and comments.
     */
    private static function skipSpaces(string $code, int $i): int
    {
        while (true) {
            $i += strspn($code, self::SPACES, $i);

            if ('/' !== ($code[$i] ?? '')) {
                return $i;
            }

            if ('/' === $c = $code[$i + 1] ?? '') {
                $i += 2 + strcspn($code, "\n\r", $i + 2);
            } elseif ('*' !== $c) {
                return $i;
            } elseif (false === $i = strpos($code, '*/', $i + 2)) {
                return \strlen($code);
            } else {
                $i += 2;
            }
        }
    }

    /**
     * Returns the offset of the last byte of the string that starts at the given offset.
     */
    private static function skipString(string $code, int $i, ?bool &$isClosed = null): int
    {
        $isClosed = false;
        $quote = $code[$i];
        $length = \strlen($code);

        while (++$i < $length) {
            $i += strcspn($code, $quote."\\\n\r", $i);

            if ($quote === $c = $code[$i] ?? '') {
                $isClosed = true;

                return $i;
            }

            if ('\\' !== $c) {
                return $i - 1;
            }

            if ("\r" === ($code[++$i] ?? '') && "\n" === ($code[$i + 1] ?? '')) {
                ++$i;
            }
        }

        return $length - 1;
    }

    /**
     * Returns the offset of the backtick that closes a template literal, or of the brace that opens its next substitution.
     */
    private static function skipTemplate(string $code, int $i, ?bool &$isSubstitution = null): int
    {
        $isSubstitution = false;
        $length = \strlen($code);

        while ($i < $length) {
            $i += strcspn($code, '`\\$', $i);

            if ('`' === $c = $code[$i] ?? '') {
                return $i;
            }

            if ('$' === $c && '{' === ($code[$i + 1] ?? '')) {
                $isSubstitution = true;

                return $i + 1;
            }

            $i += '\\' === $c ? 2 : 1;
        }

        return $length;
    }

    /**
     * Returns the offset of the last byte of the regular expression that starts at the given offset, or -1 when there is none.
     */
    private static function skipRegex(string $code, int $i): int
    {
        $isClass = false;
        $length = \strlen($code);

        while (++$i < $length) {
            $i += strcspn($code, $isClass ? "]\\\n\r" : "/[\\\n\r", $i);

            switch ($code[$i] ?? "\n") {
                case '/':
                    return $i + strcspn($code, self::DELIMITERS, $i + 1);

                case '[':
                case ']':
                    $isClass = !$isClass;
                    break;

                case '\\':
                    if (!str_contains("\n\r", $code[++$i] ?? "\n")) {
                        break;
                    }
                    // no break

                default:
                    return -1;
            }
        }

        return -1;
    }

    /**
     * Returns the offset of the brace that closes the list of names that starts at the given offset, or -1 when the list is invalid.
     */
    private static function skipNamedBindings(string $code, int $i): int
    {
        $i = self::skipSpaces($code, $i + 1);

        while ('}' !== $c = $code[$i] ?? '') {
            if ('"' === $c || "'" === $c) {
                $i = self::skipString($code, $i, $isClosed);

                if (!$isClosed) {
                    return -1;
                }
            } elseif (',' !== $c) {
                if (!$n = strcspn($code, self::DELIMITERS, $i)) {
                    return -1;
                }

                $i += $n - 1;
            }

            $i = self::skipSpaces($code, $i + 1);
        }

        return $i;
    }

    /**
     * Returns the offset of the quote that closes the string that starts at the given offset, or -1 when there is no plain string.
     */
    private static function findPlainStringEnd(string $code, int $i): int
    {
        $c = $code[$i] ?? '';

        if ('`' === $c) {
            $end = self::skipTemplate($code, $i + 1, $isSubstitution);
            $isClosed = !$isSubstitution && $end < \strlen($code);
        } elseif ('"' === $c || "'" === $c) {
            $end = self::skipString($code, $i, $isClosed);
        } else {
            return -1;
        }

        return $isClosed ? $end : -1;
    }
}
