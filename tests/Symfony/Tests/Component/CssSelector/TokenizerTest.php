<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Tests\Component\CssSelector;

use Symfony\Component\CssSelector\Tokenizer;

class TokenizerTest extends \PHPUnit_Framework_TestCase
{
    protected $tokenizer;

    protected function setUp()
    {
        $this->tokenizer = new Tokenizer();
    }

    /**
     * @dataProvider getCssSelectors
     */
    public function testTokenize($css)
    {
        $this->assertEquals($css, $this->tokensToString($this->tokenizer->tokenize($css)), '->tokenize() lexes an input string and returns an array of tokens');
    }

    public function testTokenizeWithQuotedStrings()
    {
        $this->assertEquals('foo[class=foo bar  ]', $this->tokensToString($this->tokenizer->tokenize('foo[class="foo bar"]')), '->tokenize() lexes an input string and returns an array of tokens');
        $this->assertEquals("foo[class=foo Abar     ]", $this->tokensToString($this->tokenizer->tokenize('foo[class="foo \\65 bar"]')), '->tokenize() lexes an input string and returns an array of tokens');
    }

    /**
     * @expectedException Symfony\Component\CssSelector\Exception\ParseException
     */
    public function testTokenizeInvalidString()
    {
        $this->tokensToString($this->tokenizer->tokenize('/invalid'));
    }

    public function getCssSelectors()
    {
        return array(
            array('h1'),
            array('h1:nth-child(3n+1)'),
            array('h1 > p'),
            array('h1#foo'),
            array('h1.foo'),
            array('h1[class*=foo]'),
            array('h1 .foo'),
            array('h1 #foo'),
            array('h1 [class*=foo]'),
        );
    }

    protected function tokensToString($tokens)
    {
        $str = '';
        foreach ($tokens as $token) {
            $str .= str_repeat(' ', $token->getPosition() - strlen($str)).$token;
        }

        return $str;
    }
}
