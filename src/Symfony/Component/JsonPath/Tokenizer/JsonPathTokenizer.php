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
    public const SINGULAR_ARGUMENT_FUNCTIONS = ['length', 'match', 'search'];
    public const RFC9535_FUNCTION_ARITY = [
        'length' => 1,
        'count' => 1,
        'value' => 1,
        'match' => 2,
        'search' => 2,
    ];

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
        return \in_array($char, [' ', "\t", "\n", "\r"], true);
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
                if (self::containsNonSingularRelativeComparisonQuery($left) || self::containsNonSingularRelativeComparisonQuery($right)) {
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

        if (preg_match('/(?<!["\'])\b(True|False|Null)\b(?!["\'])/', $filterExpr)) {
            throw new InvalidJsonPathException('Incorrectly capitalized literal in filter expression.', $position);
        }

        if (preg_match('/^(length|count|value)\s*\([^)]*\)$/', $filterExpr)) {
            throw new InvalidJsonPathException('Function result must be compared.', $position);
        }

        foreach (self::parseFunctionCalls($filterExpr) as [$functionName, $args]) {
            self::validateFunctionArguments($functionName, $args, $position);
        }

        if (preg_match('/^'.self::BARE_LITERAL_REGEX.'$/', $filterExpr)) {
            throw new InvalidJsonPathException('Bare literal in filter expression - literals must be compared.', $position);
        }

        if (preg_match('/\b'.self::BARE_LITERAL_REGEX.'\s*(&&|\|\|)\s*'.self::BARE_LITERAL_REGEX.'\b/', $filterExpr)) {
            throw new InvalidJsonPathException('Bare literals in logical expression - literals must be compared.', $position);
        }

        if (preg_match('/\b(length|count|value|match|search)\s*\([^)]*\)\s*[=!]=\s*(true|false)\b/', $filterExpr)
            || preg_match('/\b(true|false)\s*[=!]=\s*(length|count|value|match|search)\s*\([^)]*\)/', $filterExpr)) {
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

    public static function isNonSingularRelativeQuery(string $query): bool
    {
        if (!str_starts_with($query = trim($query), '@')) {
            return false;
        }

        if ('@.*' === $query || preg_match('/@(?:\.\.|.*\[.*:.*]|.*\[.*,.*])/', $query)) {
            return true;
        }

        return false;
    }

    private static function containsNonSingularRelativeComparisonQuery(string $query): bool
    {
        if (!str_starts_with($query = trim($query), '@')) {
            return false;
        }

        return preg_match('/@(?:\.\.|.*\[\*]|.*\.\*|.*\[.*:.*]|.*\[.*,.*])/', $query);
    }

    private static function validateFunctionArguments(string $functionName, string $args, int $position): void
    {
        if (!isset(self::RFC9535_FUNCTION_ARITY[$functionName])) {
            return;
        }

        $argStrings = ($args = trim($args)) ? JsonPathUtils::parseCommaSeparatedValues($args) : [];
        $expectedArgCount = self::RFC9535_FUNCTION_ARITY[$functionName];

        if (\count($argStrings) !== $expectedArgCount) {
            throw new InvalidJsonPathException(\sprintf('the JsonPath function "%s" requires exactly %d argument(s).', $functionName, $expectedArgCount), $position);
        }

        if ('count' === $functionName && isset($argStrings[0])) {
            $arg = trim($argStrings[0]);
            if (preg_match('/^(true|false|null|\d+(\.\d+)?([eE][+-]?\d+)?|\'[^\']*\'|"[^"]*")$/', $arg)) {
                throw new InvalidJsonPathException(\sprintf('the JsonPath function "%s" requires a query argument, not a literal.', $functionName), $position);
            }
        }

        if (\in_array($functionName, self::SINGULAR_ARGUMENT_FUNCTIONS, true) && isset($argStrings[0])) {
            $arg = trim($argStrings[0]);
            if (self::isNonSingularRelativeQuery($arg)) {
                throw new InvalidJsonPathException(\sprintf('the JsonPath function "%s" argument must be a singular query.', $functionName), $position);
            }
        }
    }

    /**
     * @return list<array{0: string, 1: string}>
     */
    private static function parseFunctionCalls(string $expr): array
    {
        $calls = [];
        $length = \strlen($expr);
        $inQuote = false;
        $quoteChar = '';

        for ($i = 0; $i < $length; ++$i) {
            $char = $expr[$i];

            if ('\\' === $char && $inQuote && isset($expr[$i + 1])) {
                ++$i;
                continue;
            }

            if ('"' === $char || "'" === $char) {
                if (!$inQuote) {
                    $inQuote = true;
                    $quoteChar = $char;
                } elseif ($quoteChar === $char) {
                    $inQuote = false;
                    $quoteChar = '';
                }

                continue;
            }

            if ($inQuote || !(ctype_alnum($char) || '_' === $char)) {
                continue;
            }

            $start = $i;
            while (isset($expr[$i + 1]) && (ctype_alnum($expr[$i + 1]) || '_' === $expr[$i + 1])) {
                ++$i;
            }

            $functionName = substr($expr, $start, $i - $start + 1);

            if (\in_array($functionName, ['true', 'false', 'null'], true)) {
                continue;
            }

            $openParenPos = $i + 1;
            while (isset($expr[$openParenPos]) && ctype_space($expr[$openParenPos])) {
                ++$openParenPos;
            }

            if (!isset($expr[$openParenPos]) || '(' !== $expr[$openParenPos]) {
                continue;
            }

            $args = self::extractParenthesizedExpression($expr, $openParenPos);
            if (null === $args) {
                continue;
            }

            $calls[] = [$functionName, trim($args)];
            array_push($calls, ...self::parseFunctionCalls($args));
            $i = $openParenPos + \strlen($args) + 1;
        }

        return $calls;
    }

    private static function extractParenthesizedExpression(string $expr, int $openParenPos): ?string
    {
        if (!isset($expr[$openParenPos]) || '(' !== $expr[$openParenPos]) {
            return null;
        }

        $depth = 0;
        $length = \strlen($expr);
        $inQuote = false;
        $quoteChar = '';

        for ($i = $openParenPos; $i < $length; ++$i) {
            $char = $expr[$i];

            if ('\\' === $char && $inQuote && isset($expr[$i + 1])) {
                ++$i;
                continue;
            }

            if ('"' === $char || "'" === $char) {
                if (!$inQuote) {
                    $inQuote = true;
                    $quoteChar = $char;
                } elseif ($quoteChar === $char) {
                    $inQuote = false;
                    $quoteChar = '';
                }

                continue;
            }

            if ($inQuote) {
                continue;
            }

            if ('(' === $char) {
                ++$depth;
            } elseif (')' === $char) {
                --$depth;
                if (0 === $depth) {
                    return substr($expr, $openParenPos + 1, $i - $openParenPos - 1);
                }
            }
        }

        return null;
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
