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
 * CSS selector tokenizer patterns builder.
 *
 * This component is a port of the Python lxml library,
 * which is copyright Infrae and distributed under the BSD license.
 *
 * @author Jean-François Simon <jeanfrancois.simon@sensiolabs.com>
 */
class TokenizerPatterns
{
    private $unicodeEscapePattern;
    private $escapePattern;
    private $stringEscapePattern;
    private $nonAsciiPattern;
    private $nmCharPattern;
    private $nmStartPattern;
    private $identifierPattern;
    private $hashPattern;
    private $numberPattern;
    private $quotedStringPattern;

    public function __construct()
    {
        $this->unicodeEscapePattern = '\\([0-9a-f]{1,6})(?:\r\n|[ \n\r\t\f])?';
        $this->escapePattern = $this->unicodeEscapePattern.'|\\[^\n\r\f0-9a-f]';
        $this->stringEscapePattern = '\\(?:\n|\r\n|\r|\f)|'.$this->escapePattern;
        $this->nonAsciiPattern = '[^\0-\177]';
        $this->nmCharPattern = '[_a-z0-9-]|'.$this->escapePattern.'|'.$this->nonAsciiPattern;
        $this->nmStartPattern = '[_a-z]|'.$this->escapePattern.'|'.$this->nonAsciiPattern;
        $this->identifierPattern = '(?:'.$this->nmStartPattern.')(?:'.$this->nmCharPattern.')*';
        $this->hashPattern = '#(?:'.$this->nmCharPattern.')+';
        $this->numberPattern = '[+-]?(?:[0-9]*\.[0-9]+|[0-9]+)';
        $this->quotedStringPattern = '([^\n\r\f%s]|'.$this->stringEscapePattern.')*';
    }

    public function getUnicodeEscapePattern()
    {
        return '~'.$this->unicodeEscapePattern.'~iU';
    }

    public function getIdentifierPattern()
    {
        return '~'.$this->identifierPattern.'~i';
    }

    public function getHashPattern()
    {
        return '~'.$this->hashPattern.'~iU';
    }

    public function getNumberPattern()
    {
        return '~'.$this->numberPattern.'~';
    }

    public function getQuotedStringPattern($quote)
    {
        return '~'.sprintf($this->quotedStringPattern, $quote).'~iU';
    }
}
