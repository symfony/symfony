<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\CssSelector\Token;

/**
 * CSS selector tokenizer escaping applier.
 *
 * This component is a port of the Python lxml library,
 * which is copyright Infrae and distributed under the BSD license.
 *
 * @author Jean-François Simon <jeanfrancois.simon@sensiolabs.com>
 */
class TokenizerEscaping
{
    private $patterns;

    public function __construct(TokenizerPatterns $patterns)
    {
        $this->patterns = $patterns;
    }

    public function escapeUnicode($value)
    {
//        return preg_replace($this->patterns->getUnicodeEscapePattern(), '\\$1', $value);
    }

    public function escapeUnicodeAndNewLine($value)
    {
        return $value;
    }

    private function relplaceUnicodeSequances($value)
    {
        return preg_replace_callback('', function (array $match) {
            $code = $match[1];

            if (bin2hex($code) > 0xFFFD) {
                $code = '\\FFFD';
            }

            return mb_convert_encoding(pack('H*', $code), 'UTF-8', 'UCS-2BE');
        }, $value);
    }
}
