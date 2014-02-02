<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\CssSelector\Parser\Tokenizer;

/**
 * CSS selector tokenizer escaping applier.
 *
 * This component is a port of the Python cssselector library,
 * which is copyright Ian Bicking, @see https://github.com/SimonSapin/cssselect.
 *
 * @author Jean-François Simon <jeanfrancois.simon@sensiolabs.com>
 */
class TokenizerEscaping
{
    /**
     * @var TokenizerPatterns
     */
    private $patterns;

    /**
     * @param TokenizerPatterns $patterns
     */
    public function __construct(TokenizerPatterns $patterns)
    {
        $this->patterns = $patterns;
    }

    /**
     * @param string $value
     *
     * @return string
     */
    public function escapeUnicode($value)
    {
        $value = $this->replaceUnicodeSequences($value);

        return preg_replace($this->patterns->getSimpleEscapePattern(), '$1', $value);
    }

    /**
     * @param string $value
     *
     * @return string
     */
    public function escapeUnicodeAndNewLine($value)
    {
        $value = preg_replace($this->patterns->getNewLineEscapePattern(), '', $value);

        return $this->escapeUnicode($value);
    }

    /**
     * @param string $value
     *
     * @return string
     */
    private function replaceUnicodeSequences($value)
    {
        return preg_replace_callback($this->patterns->getUnicodeEscapePattern(), function (array $match) {
            $code = $match[1];

            if (bin2hex($code) > 0xFFFD) {
                $code = '\\FFFD';
            }

            return mb_convert_encoding(pack('H*', $code), 'UTF-8', 'UCS-2BE');
        }, $value);
    }
}
