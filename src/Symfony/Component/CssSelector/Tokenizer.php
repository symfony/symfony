<?php

namespace Symfony\Component\CssSelector;

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * Tokenizer lexes a CSS Selector to tokens.
 *
 * This component is a port of the Python lxml library,
 * which is copyright Infrae and distributed under the BSD license.
 *
 * @author Fabien Potencier <fabien.potencier@symfony-project.com>
 */
class Tokenizer
{
    public function tokenize($s)
    {
        if (function_exists('mb_internal_encoding') && ((int) ini_get('mbstring.func_overload')) & 2) {
            $mbEncoding = mb_internal_encoding();
            mb_internal_encoding('ASCII');
        }

        $tokens = array();
        $pos = 0;
        $s = preg_replace('#/\*.*?\*/#s', '', $s);

        while (1) {
            if (preg_match('#\s+#A', $s, $match, 0, $pos)) {
                $preceding_whitespace_pos = $pos;
                $pos += strlen($match[0]);
            } else {
                $preceding_whitespace_pos = 0;
            }

            if ($pos >= strlen($s)) {
                if (isset($mbEncoding)) {
                    mb_internal_encoding($mbEncoding);
                }

                return $tokens;
            }

            if (preg_match('#[+-]?\d*n(?:[+-]\d+)?#A', $s, $match, 0, $pos) && 'n' !== $match[0]) {
                $sym = substr($s, $pos, strlen($match[0]));
                $tokens[] = new Token('Symbol', $sym, $pos);
                $pos += strlen($match[0]);

                continue;
            }

            $c = $s[$pos];
            $c2 = substr($s, $pos, 2);
            if (in_array($c2, array('~=', '|=', '^=', '$=', '*=', '::', '!='))) {
                $tokens[] = new Token('Token', $c2, $pos);
                $pos += 2;

                continue;
            }

            if (in_array($c, array('>', '+', '~', ',', '.', '*', '=', '[', ']', '(', ')', '|', ':', '#'))) {
                if (in_array($c, array('.', '#', '[')) && $preceding_whitespace_pos > 0) {
                    $tokens[] = new Token('Token', ' ', $preceding_whitespace_pos);
                }
                $tokens[] = new Token('Token', $c, $pos);
                ++$pos;

                continue;
            }

            if ($c === '"' || $c === "'") {
                // Quoted string
                $old_pos = $pos;
                list($sym, $pos) = $this->tokenizeEscapedString($s, $pos);

                $tokens[] = new Token('String', $sym, $old_pos);

                continue;
            }

            $old_pos = $pos;
            list($sym, $pos) = $this->tokenizeSymbol($s, $pos);

            $tokens[] = new Token('Symbol', $sym, $old_pos);

            continue;
        }
    }

    /**
     * @throws SyntaxError When expected closing is not found
     */
    protected function tokenizeEscapedString($s, $pos)
    {
        $quote = $s[$pos];

        $pos = $pos + 1;
        $start = $pos;
        while (1) {
            $next = strpos($s, $quote, $pos);
            if (false === $next) {
                throw new SyntaxError(sprintf('Expected closing %s for string in: %s', $quote, substr($s, $start)));
            }

            $result = substr($s, $start, $next - $start);
            if ('\\' === $result[strlen($result) - 1]) {
                // next quote character is escaped
                $pos = $next + 1;
                continue;
            }

            if (false !== strpos($result, '\\')) {
                $result = $this->unescapeStringLiteral($result);
            }

            return array($result, $next + 1);
        }
    }

    /**
     * @throws SyntaxError When invalid escape sequence is found
     */
    protected function unescapeStringLiteral($literal)
    {
        return preg_replace_callback('#(\\\\(?:[A-Fa-f0-9]{1,6}(?:\r\n|\s)?|[^A-Fa-f0-9]))#', function ($matches) use ($literal)
        {
            if ($matches[0][0] == '\\' && strlen($matches[0]) > 1) {
                $matches[0] = substr($matches[0], 1);
                if (in_array($matches[0][0], array('0', '1', '2', '3', '4', '5', '6', '7', '8', '9', 'A', 'B', 'C', 'D', 'E', 'F', 'a', 'b', 'c', 'd', 'e', 'f'))) {
                    return chr(trim($matches[0]));
                }
            } else {
                throw new SyntaxError(sprintf('Invalid escape sequence %s in string %s', $matches[0], $literal));
            }
        }, $literal);
    }

    /**
     * @throws SyntaxError When Unexpected symbol is found
     */
    protected function tokenizeSymbol($s, $pos)
    {
        $start = $pos;

        if (!preg_match('#[^\w\-]#', $s, $match, PREG_OFFSET_CAPTURE, $pos)) {
            // Goes to end of s
            return array(substr($s, $start), strlen($s));
        }

        $matchStart = $match[0][1];

        if ($matchStart == $pos) {
            throw new SyntaxError(sprintf('Unexpected symbol: %s at %s', $s[$pos], $pos));
        }

        $result = substr($s, $start, $matchStart - $start);
        $pos = $matchStart;

        return array($result, $pos);
    }
}
