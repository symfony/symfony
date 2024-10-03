<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\ExpressionLanguage;

/**
 * Lexes an expression.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class Lexer
{
    /**
     * Tokenizes an expression.
     *
     * @throws SyntaxError
     */
    public function tokenize(string $expression): TokenStream
    {
        $expression = str_replace(["\r", "\n", "\t", "\v", "\f"], ' ', $expression);
        $cursor = 0;
        $tokens = [];
        $brackets = [];
        $end = \strlen($expression);

        while ($cursor < $end) {
            if (' ' == $expression[$cursor]) {
                ++$cursor;

                continue;
            }

            if (preg_match('/
                (?(DEFINE)(?P<LNUM>[0-9]+(_[0-9]+)*))
                (?:\.(?&LNUM)|(?&LNUM)(?:\.(?!\.)(?&LNUM)?)?)(?:[eE][+-]?(?&LNUM))?/Ax',
                $expression, $match, 0, $cursor)
            ) {
                // numbers
                $tokens[] = new Token(Token::NUMBER_TYPE, 0 + str_replace('_', '', $match[0]), $cursor + 1);
                $cursor += \strlen($match[0]);
            } elseif (str_contains('([{', $expression[$cursor])) {
                // opening bracket
                $brackets[] = [$expression[$cursor], $cursor];

                $tokens[] = new Token(Token::PUNCTUATION_TYPE, $expression[$cursor], $cursor + 1);
                ++$cursor;
            } elseif (str_contains(')]}', $expression[$cursor])) {
                // closing bracket
                if (!$brackets) {
                    throw new SyntaxError(\sprintf('Unexpected "%s".', $expression[$cursor]), $cursor, $expression);
                }

                [$expect, $cur] = array_pop($brackets);
                if ($expression[$cursor] != strtr($expect, '([{', ')]}')) {
                    throw new SyntaxError(\sprintf('Unclosed "%s".', $expect), $cur, $expression);
                }

                $tokens[] = new Token(Token::PUNCTUATION_TYPE, $expression[$cursor], $cursor + 1);
                ++$cursor;
            } elseif (preg_match('/"([^"\\\\]*(?:\\\\.[^"\\\\]*)*)"|\'([^\'\\\\]*(?:\\\\.[^\'\\\\]*)*)\'/As', $expression, $match, 0, $cursor)) {
                // strings
                $tokens[] = new Token(Token::STRING_TYPE, stripcslashes(substr($match[0], 1, -1)), $cursor + 1);
                $cursor += \strlen($match[0]);
            } elseif (preg_match('{/\*.*?\*/}A', $expression, $match, 0, $cursor)) {
                // comments
                $cursor += \strlen($match[0]);
            } elseif (preg_match('/(?<=^|[\s(])starts with(?=[\s(])|(?<=^|[\s(])ends with(?=[\s(])|(?<=^|[\s(])contains(?=[\s(])|(?<=^|[\s(])matches(?=[\s(])|(?<=^|[\s(])not in(?=[\s(])|(?<=^|[\s(])not(?=[\s(])|(?<=^|[\s(])xor(?=[\s(])|(?<=^|[\s(])and(?=[\s(])|\=\=\=|\!\=\=|(?<=^|[\s(])or(?=[\s(])|\|\||&&|\=\=|\!\=|\>\=|\<\=|(?<=^|[\s(])in(?=[\s(])|\.\.|\*\*|\<\<|\>\>|\!|\||\^|&|\<|\>|\+|\-|~|\*|\/|%/A', $expression, $match, 0, $cursor)) {
                // operators
                $tokens[] = new Token(Token::OPERATOR_TYPE, $match[0], $cursor + 1);
                $cursor += \strlen($match[0]);
            } elseif ('?' === $expression[$cursor] && '.' === ($expression[$cursor + 1] ?? '')) {
                // null-safe
                $tokens[] = new Token(Token::PUNCTUATION_TYPE, '?.', ++$cursor);
                ++$cursor;
            } elseif ('?' === $expression[$cursor] && '?' === ($expression[$cursor + 1] ?? '')) {
                // null-coalescing
                $tokens[] = new Token(Token::PUNCTUATION_TYPE, '??', ++$cursor);
                ++$cursor;
            } elseif (str_contains('.,?:', $expression[$cursor])) {
                // punctuation
                $tokens[] = new Token(Token::PUNCTUATION_TYPE, $expression[$cursor], $cursor + 1);
                ++$cursor;
            } elseif (preg_match('/[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*/A', $expression, $match, 0, $cursor)) {
                // names
                $tokens[] = new Token(Token::NAME_TYPE, $match[0], $cursor + 1);
                $cursor += \strlen($match[0]);
            } else {
                // unlexable
                throw new SyntaxError(\sprintf('Unexpected character "%s".', $expression[$cursor]), $cursor, $expression);
            }
        }

        $tokens[] = new Token(Token::EOF_TYPE, null, $cursor + 1);

        if ($brackets) {
            [$expect, $cur] = array_pop($brackets);
            throw new SyntaxError(\sprintf('Unclosed "%s".', $expect), $cur, $expression);
        }

        return new TokenStream($tokens, $expression);
    }
}
