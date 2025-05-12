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

/**
 * @author Alexandre Daubois <alex.daubois@gmail.com>
 *
 * @internal
 */
final class JsonPathTokenizer
{
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

        $chars = mb_str_split((string) $query);
        $length = \count($chars);

        if (0 === $length) {
            throw new InvalidJsonPathException('empty JSONPath expression.');
        }

        if ('$' !== $chars[0]) {
            throw new InvalidJsonPathException('expression must start with $.');
        }

        for ($i = 0; $i < $length; ++$i) {
            $char = $chars[$i];
            $position = $i;

            if (('"' === $char || "'" === $char) && !$inQuote) {
                $inQuote = true;
                $quoteChar = $char;
                $current .= $char;
                continue;
            }

            if ($inQuote) {
                $current .= $char;
                if ($char === $quoteChar && '\\' !== $chars[$i - 1]) {
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
                continue;
            }

            if (']' === $char) {
                if ($inFilter && $filterParenthesisDepth > 0) {
                    $current .= $char;
                    continue;
                }

                if (--$bracketDepth < 0) {
                    throw new InvalidJsonPathException('unmatched closing bracket.', $position);
                }

                if (0 === $bracketDepth) {
                    if ('' === $current) {
                        throw new InvalidJsonPathException('empty brackets are not allowed.', $position);
                    }

                    $tokens[] = new JsonPathToken(TokenType::Bracket, $current);
                    $current = '';
                    $inBracket = false;
                    $inFilter = false;
                    $filterParenthesisDepth = 0;
                    continue;
                }
            }

            if ('?' === $char && $inBracket && !$inFilter) {
                if ('' !== $current) {
                    throw new InvalidJsonPathException('unexpected characters before filter expression.', $position);
                }
                $inFilter = true;
                $filterParenthesisDepth = 0;
            }

            if ($inFilter) {
                if ('(' === $char) {
                    ++$filterParenthesisDepth;
                } elseif (')' === $char) {
                    if (--$filterParenthesisDepth < 0) {
                        throw new InvalidJsonPathException('unmatched closing parenthesis in filter.', $position);
                    }
                }
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

        if ('' !== $current) {
            // final validation of the whole name
            if (!preg_match('/^(?:\*|[a-zA-Z_\x{0080}-\x{D7FF}\x{E000}-\x{10FFFF}][a-zA-Z0-9_\x{0080}-\x{D7FF}\x{E000}-\x{10FFFF}]*)$/u', $current)) {
                throw new InvalidJsonPathException(\sprintf('invalid character in property name "%s"', $current));
            }

            $tokens[] = new JsonPathToken(TokenType::Name, $current);
        }

        return $tokens;
    }
}
