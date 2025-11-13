<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\JsonPath\Tokenizer;

use Symfony\Component\JsonPath\Exception\InvalidJsonPathException;
use Symfony\Component\JsonPath\JsonPath;
use Symfony\Component\JsonPath\JsonPathUtils;

/**
 * @author Alexandre Daubois <alex.daubois@gmail.com>
 *
 * @internal
 */
final class JsonPathTokenizer
{
    private const RFC9535_WHITESPACE_CHARS = [' ', "\t", "\n", "\r"];
    private const BARE_LITERAL_REGEX = '(true|false|null|\d+(\.\d+)?([eE][+-]?\d+)?|\'[^\']*\'|"[^"]*")';

    /**
     * @return JsonPathToken[]
     */
    public static function tokenize(JsonPath $query): array
    {
        $tokens = [];
        $current = '';
        $inBracket = false;
        $bracketDepth = 0;
        $inFilter = false;
        $inQuote = false;
        $quoteChar = '';
        $filterParenthesisDepth = 0;
        $filterBracketDepth = 0;
        $hasContentAfterRoot = false;

        $chars = mb_str_split((string) $query);
        $length = \count($chars);

        if (0 === $length) {
            throw new InvalidJsonPathException('empty JSONPath expression.');
        }

        $i = self::skipWhitespace($chars, 0, $length);
        if ($i >= $length || '$' !== $chars[$i]) {
            throw new InvalidJsonPathException('expression must start with $.');
        }

        $rootIndex = $i;
        if ($rootIndex + 1 < $length) {
            $hasContentAfterRoot = true;
        }

        for ($i = 0; $i < $length; ++$i) {
            $char = $chars[$i];
            $position = $i;

            if (!$inQuote && !$inBracket && self::isWhitespace($char)) {
                if ('' !== $current) {
                    $tokens[] = new JsonPathToken(TokenType::Name, $current);
                    $current = '';
                }

                $nextNonWhitespaceIndex = self::skipWhitespace($chars, $i, $length);
                if ($nextNonWhitespaceIndex < $length && '[' !== $chars[$nextNonWhitespaceIndex] && '.' !== $chars[$nextNonWhitespaceIndex]) {
                    throw new InvalidJsonPathException('whitespace is not allowed in property names.', $i);
                }

                $i = $nextNonWhitespaceIndex - 1;

                continue;
            }

            if (('"' === $char || "'" === $char) && !$inQuote) {
                $inQuote = true;
                $quoteChar = $char;
                $current .= $char;
                continue;
            }

            if ($inQuote) {
                // literal control characters (U+0000 through U+001F) in quoted strings
                // are not be allowed unless they are part of escape sequences
                if ($inBracket) {
                    if (\ord($char[0]) <= 31) {
                        if (!self::isEscaped($chars, $i)) {
                            throw new InvalidJsonPathException('control characters are not allowed in quoted strings.', $position);
                        }
                    }

                    if ("\n" === $char && self::isEscaped($chars, $i)) {
                        throw new InvalidJsonPathException('escaped newlines are not allowed in quoted strings.', $position);
                    }

                    if ('u' === $char && self::isEscaped($chars, $i)) {
                        self::validateUnicodeEscape($chars, $i, $position);
                    }
                }

                $current .= $char;
                if ($char === $quoteChar && !self::isEscaped($chars, $i)) {
                    $inQuote = false;
                }

                if ($i === $length - 1 && $inQuote) {
                    throw new InvalidJsonPathException('unclosed string literal.', $position);
                }
                continue;
            }

            if ('$' === $char && 0 === $i) {
                continue;
            }

            if ('[' === $char && !$inFilter) {
                if ('' !== $current) {
                    $tokens[] = new JsonPathToken(TokenType::Name, $current);
                    $current = '';
                }

                $inBracket = true;
                ++$bracketDepth;
                $i = self::skipWhitespace($chars, $i + 1, $length) - 1; // -1 because loop will increment

                continue;
            }

            if ('[' === $char && $inFilter) {
                // inside filter expressions, brackets are part of the filter content
                ++$filterBracketDepth;
                $current .= $char;
                continue;
            }

            if (']' === $char) {
                if ($inFilter && $filterBracketDepth > 0) {
                    // inside filter expressions, brackets are part of the filter content
                    --$filterBracketDepth;
                    $current .= $char;
                    continue;
                }

                if (--$bracketDepth < 0) {
                    throw new InvalidJsonPathException('unmatched closing bracket.', $position);
                }

                if (0 === $bracketDepth) {
                    if ('' === $current = trim($current)) {
                        throw new InvalidJsonPathException('empty brackets are not allowed.', $position);
                    }

                    // validate filter expressions
                    if (str_starts_with($current, '?')) {
                        if ($filterParenthesisDepth > 0) {
                            throw new InvalidJsonPathException('unclosed parenthesis.', $position);
                        }

                        if ($filterBracketDepth > 0) {
                            throw new InvalidJsonPathException('unclosed bracket.', $position);
                        }

                        self::validateFilterExpression($current, $position);
                    }

                    $tokens[] = new JsonPathToken(TokenType::Bracket, $current);
                    $current = '';
                    $inBracket = false;
                    $inFilter = false;
                    $filterParenthesisDepth = 0;
                    $filterBracketDepth = 0;
                    continue;
                }
            }

            if ('?' === $char && $inBracket && !$inFilter) {
                if ('' !== trim($current)) {
                    throw new InvalidJsonPathException('unexpected characters before filter expression.', $position);
                }

                $current = '?';
                $inFilter = true;
                $filterParenthesisDepth = 0;
                $filterBracketDepth = 0;

                continue;
            }

            if ($inFilter) {
                if ('(' === $char) {
                    if (preg_match('/\w\s+$/', $current)) {
                        throw new InvalidJsonPathException('whitespace is not allowed between function name and parenthesis.', $position);
                    }
                    ++$filterParenthesisDepth;
                } elseif (')' === $char) {
                    if (--$filterParenthesisDepth < 0) {
                        throw new InvalidJsonPathException('unmatched closing parenthesis in filter.', $position);
                    }
                }
                $current .= $char;

                continue;
            }

            if ($inBracket && self::isWhitespace($char)) {
                $current .= $char;

                continue;
            }

            // recursive descent
            if ('.' === $char && !$inBracket) {
                if ('' !== $current) {
                    $tokens[] = new JsonPathToken(TokenType::Name, $current);
                    $current = '';
                }

                if ($i + 1 < $length && '.' === $chars[$i + 1]) {
                    // more than two consecutive dots?
                    if ($i + 2 < $length && '.' === $chars[$i + 2]) {
                        throw new InvalidJsonPathException('invalid character "." in property name.', $i + 2);
                    }

                    $tokens[] = new JsonPathToken(TokenType::Recursive, '..');
                    ++$i;
                } elseif ($i + 1 >= $length) {
                    throw new InvalidJsonPathException('path cannot end with a dot.', $position);
                }

                continue;
            }

            $current .= $char;
        }

        if ($inBracket) {
            throw new InvalidJsonPathException('unclosed bracket.', $length - 1);
        }

        if ($inQuote) {
            throw new InvalidJsonPathException('unclosed string literal.', $length - 1);
        }

        if ('' !== $current = trim($current)) {
            // final validation of the whole name
            if (!preg_match('/^(?:\*|[a-zA-Z_\x{0080}-\x{D7FF}\x{E000}-\x{10FFFF}][a-zA-Z0-9_\x{0080}-\x{D7FF}\x{E000}-\x{10FFFF}]*)$/u', $current)) {
                throw new InvalidJsonPathException(\sprintf('invalid character in property name "%s"', $current));
            }

            $tokens[] = new JsonPathToken(TokenType::Name, $current);
        }

        if ($hasContentAfterRoot && !$tokens) {
            throw new InvalidJsonPathException('invalid JSONPath expression.');
        }

        if (1 === \count($tokens) && TokenType::Recursive === $tokens[0]->type) {
            throw new InvalidJsonPathException('descendant segment must be followed by a selector.');
        }

        return $tokens;
    }

    private static function isWhitespace(string $char): bool
    {
        return \in_array($char, self::RFC9535_WHITESPACE_CHARS, true);
    }

    private static function isEscaped(array $chars, int $position): bool
    {
        if (0 === $position) {
            return false;
        }

        $consecutiveBackslashes = 0;
        for ($i = $position - 1; $i >= 0 && '\\' === $chars[$i]; --$i) {
            ++$consecutiveBackslashes;
        }

        return 1 === $consecutiveBackslashes % 2;
    }

    private static function skipWhitespace(array $chars, int $index, int $length): int
    {
        while ($index < $length && self::isWhitespace($chars[$index])) {
            ++$index;
        }

        return $index;
    }

    private static function validateFilterExpression(string $expr, int $position): void
    {
        self::validateBareLiterals($expr, $position);

        $filterExpr = ltrim($expr, '?');
        $filterExpr = trim($filterExpr);

        $comparisonOps = ['==', '!=', '>=', '<=', '>', '<'];
        foreach ($comparisonOps as $op) {
            if (str_contains($filterExpr, $op)) {
                [$left, $right] = array_map('trim', explode($op, $filterExpr, 2));

                // check if either side contains non-singular queries
                if (self::isNonSingularQuery($left) || self::isNonSingularQuery($right)) {
                    throw new InvalidJsonPathException('Non-singular query is not comparable.', $position);
                }

                break;
            }
        }

        // look for invalid number formats in filter expressions
        $operators = [...$comparisonOps, '&&', '||'];
        $tokens = [$filterExpr];

        foreach ($operators as $op) {
            $newTokens = [];
            foreach ($tokens as $token) {
                $newTokens = array_merge($newTokens, explode($op, $token));
            }

            $tokens = $newTokens;
        }

        foreach ($tokens as $token) {
            if (
                '' === ($token = trim($token))
                || \in_array($token, ['true', 'false', 'null'], true)
                || false !== strpbrk($token[0], '@"\'')
                || false !== strpbrk($token, '()[]$')
                || (str_contains($token, '.') && !preg_match('/^[\d+\-.eE\s]*\./', $token))
            ) {
                continue;
            }

            // strict JSON number format validation
            if (
                preg_match('/^(?=[\d+\-.eE\s]+$)(?=.*\d)/', $token)
                && !preg_match('/^-?(0|[1-9]\d*)(\.\d+)?([eE][+-]?\d+)?$/', $token)
            ) {
                throw new InvalidJsonPathException(\sprintf('Invalid number format "%s" in filter expression.', $token), $position);
            }
        }
    }

    private static function validateBareLiterals(string $expr, int $position): void
    {
        $filterExpr = ltrim($expr, '?');
        $filterExpr = trim($filterExpr);

        if (preg_match('/\b(True|False|Null)\b/', $filterExpr)) {
            throw new InvalidJsonPathException('Incorrectly capitalized literal in filter expression.', $position);
        }

        if (preg_match('/^(length|count|value)\s*\([^)]*\)$/', $filterExpr)) {
            throw new InvalidJsonPathException('Function result must be compared.', $position);
        }

        if (preg_match('/\b(length|count|value)\s*\(([^)]*)\)/', $filterExpr, $matches)) {
            $functionName = $matches[1];
            $args = trim($matches[2]);
            if (!$args) {
                throw new InvalidJsonPathException('Function requires exactly one argument.', $position);
            }

            $argParts = JsonPathUtils::parseCommaSeparatedValues($args);
            if (1 !== \count($argParts)) {
                throw new InvalidJsonPathException('Function requires exactly one argument.', $position);
            }

            $arg = trim($argParts[0]);

            if ('count' === $functionName && preg_match('/^'.self::BARE_LITERAL_REGEX.'$/', $arg)) {
                throw new InvalidJsonPathException('count() function requires a query argument, not a literal.', $position);
            }

            if ('length' === $functionName && preg_match('/@\.\*/', $arg)) {
                throw new InvalidJsonPathException('Function argument must be a singular query.', $position);
            }
        }

        if (preg_match('/\b(match|search)\s*\(([^)]*)\)/', $filterExpr, $matches)) {
            $args = trim($matches[2]);
            if (!$args) {
                throw new InvalidJsonPathException('Function requires exactly two arguments.', $position);
            }

            $argParts = JsonPathUtils::parseCommaSeparatedValues($args);
            if (2 !== \count($argParts)) {
                throw new InvalidJsonPathException('Function requires exactly two arguments.', $position);
            }
        }

        if (preg_match('/^'.self::BARE_LITERAL_REGEX.'$/', $filterExpr)) {
            throw new InvalidJsonPathException('Bare literal in filter expression - literals must be compared.', $position);
        }

        if (preg_match('/\b'.self::BARE_LITERAL_REGEX.'\s*(&&|\|\|)\s*'.self::BARE_LITERAL_REGEX.'\b/', $filterExpr)) {
            throw new InvalidJsonPathException('Bare literals in logical expression - literals must be compared.', $position);
        }

        if (preg_match('/\b(match|search|length|count|value)\s*\([^)]*\)\s*[=!]=\s*(true|false)\b/', $filterExpr)
            || preg_match('/\b(true|false)\s*[=!]=\s*(match|search|length|count|value)\s*\([^)]*\)/', $filterExpr)) {
            throw new InvalidJsonPathException('Function result cannot be compared to boolean literal.', $position);
        }

        if (preg_match('/\b'.self::BARE_LITERAL_REGEX.'\s*(&&|\|\|)/', $filterExpr)
            || preg_match('/(&&|\|\|)\s*'.self::BARE_LITERAL_REGEX.'\b/', $filterExpr)) {
            // check if the literal is not part of a comparison
            if (!preg_match('/(@[^=<>!]*|[^=<>!@]+)\s*[=<>!]+\s*'.self::BARE_LITERAL_REGEX.'/', $filterExpr)
                && !preg_match('/'.self::BARE_LITERAL_REGEX.'\s*[=<>!]+\s*(@[^=<>!]*|[^=<>!@]+)/', $filterExpr)
            ) {
                throw new InvalidJsonPathException('Bare literal in logical expression - literals must be compared.', $position);
            }
        }
    }

    private static function isNonSingularQuery(string $query): bool
    {
        if (!str_starts_with($query = trim($query), '@')) {
            return false;
        }

        if (preg_match('/@(\.\.)|(.*\[\*])|(.*\.\*)|(.*\[.*:.*])|(.*\[.*,.*])/', $query)) {
            return true;
        }

        return false;
    }

    private static function validateUnicodeEscape(array $chars, int $index, int $position): void
    {
        if ($index + 4 >= \count($chars)) {
            return;
        }

        $hexDigits = '';
        for ($i = 1; $i <= 4; ++$i) {
            $hexDigits .= $chars[$index + $i];
        }

        if (!preg_match('/^[0-9A-Fa-f]{4}$/', $hexDigits)) {
            return;
        }

        $codePoint = hexdec($hexDigits);

        if ($codePoint >= 0xD800 && $codePoint <= 0xDBFF) {
            $nextIndex = $index + 5;

            if ($nextIndex + 1 < \count($chars)
                && '\\' === $chars[$nextIndex] && 'u' === $chars[$nextIndex + 1]
            ) {
                $nextHexDigits = '';
                for ($i = 2; $i <= 5; ++$i) {
                    $nextHexDigits .= $chars[$nextIndex + $i];
                }

                if (preg_match('/^[0-9A-Fa-f]{4}$/', $nextHexDigits)) {
                    $nextCodePoint = hexdec($nextHexDigits);

                    // high surrogate must be followed by low surrogate
                    if ($nextCodePoint < 0xDC00 || $nextCodePoint > 0xDFFF) {
                        throw new InvalidJsonPathException('Invalid Unicode surrogate pair.', $position);
                    }
                }
            } else {
                // high surrogate not followed by low surrogate
                throw new InvalidJsonPathException('Invalid Unicode surrogate pair.', $position);
            }
        } elseif ($codePoint >= 0xDC00 && $codePoint <= 0xDFFF) {
            $prevIndex = $index - 7; // position of \ in previous \uXXXX (7 positions back: u+4hex+\+u)

            if ($prevIndex >= 0
                && '\\' === $chars[$prevIndex] && 'u' === $chars[$prevIndex + 1]
            ) {
                $prevHexDigits = '';
                for ($i = 2; $i <= 5; ++$i) {
                    $prevHexDigits .= $chars[$prevIndex + $i];
                }

                if (preg_match('/^[0-9A-Fa-f]{4}$/', $prevHexDigits)) {
                    $prevCodePoint = hexdec($prevHexDigits);

                    // low surrogate must be preceded by high surrogate
                    if ($prevCodePoint < 0xD800 || $prevCodePoint > 0xDBFF) {
                        throw new InvalidJsonPathException('Invalid Unicode surrogate pair.', $position);
                    }
                }
            } else {
                // low surrogate not preceded by high surrogate
                throw new InvalidJsonPathException('Invalid Unicode surrogate pair.', $position);
            }
        }
    }
}
